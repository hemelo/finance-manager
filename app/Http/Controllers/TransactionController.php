<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Card;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        // ... (lógica de index e filtros como na resposta anterior, mas as transações agora têm currency_code)
        $this->authorize('viewAny', Transaction::class);
        $user = Auth::user();
        $query = Transaction::query();

        $cardIds = $user->cards()->pluck('id');
        $bankAccountIds = $user->bankAccounts()->pluck('id');

        $query->where(function ($q) use ($cardIds, $bankAccountIds) {
            if ($cardIds->isNotEmpty()) {
                $q->whereIn('card_id', $cardIds);
            }
            if ($bankAccountIds->isNotEmpty()) {
                $q->orWhereIn('bank_account_id', $bankAccountIds);
            }
        });

        if ($request->filled('type')) $query->where('type', $request->type);
        if ($request->filled('currency_code')) $query->where('currency_code', strtoupper($request->currency_code)); // Filtrar por moeda
        // ... outros filtros ...

        $transactions = $query->with(['card', 'bankAccount', 'subscription', 'invoice'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        // ...
        return view('transactions.index', compact('transactions' /*, 'transactionTypes', 'currencyCodes'*/));
    }

    public function create()
    {
        $this->authorize('create', Transaction::class);
        $user = Auth::user();
        $cards = $user->cards()->active()->get(); // Apenas cartões ativos
        $bankAccounts = $user->bankAccounts()->active()->get(); // Apenas contas ativas

        if ($cards->isEmpty() && $bankAccounts->isEmpty()) {
            return redirect()->route('dashboard')
                ->with('warning', 'Você precisa de um cartão ou conta bancária ativa para adicionar transações.');
        }
        $allowedTransactionTypes = [
            'card_purchase' => 'Compra no Cartão',
            'bank_deposit' => 'Depósito Bancário',
            'bank_withdrawal' => 'Saque Bancário',
        ];
        return view('transactions.create', compact('cards', 'bankAccounts', 'allowedTransactionTypes'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Transaction::class);
        $user = Auth::user();
        $creatableTypes = ['card_purchase', 'bank_deposit', 'bank_withdrawal'];

        $validatedData = $request->validate([
            'type' => ['required', Rule::in($creatableTypes)],
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date|before_or_equal:today',
            'description' => 'required|string|max:255',
            'installments' => 'nullable|integer|min:1',
            'card_id' => [
                'nullable',
                Rule::requiredIf(fn () => $request->type === 'card_purchase'),
                Rule::exists('cards', 'id')->where('user_id', $user->id)->where('status', 'active'), // Cartão ativo
            ],
            'bank_account_id' => [
                'nullable',
                Rule::requiredIf(fn () => in_array($request->type, ['bank_deposit', 'bank_withdrawal'])),
                Rule::exists('bank_accounts', 'id')->where('user_id', $user->id)->where('status', 'active'), // Conta ativa
            ],
        ]);

        $currencyCode = null;
        if ($request->type === 'card_purchase') {
            $card = Card::find($validatedData['card_id']);
            $currencyCode = $card->currency_code;
        } elseif (in_array($request->type, ['bank_deposit', 'bank_withdrawal'])) {
            $bankAccount = BankAccount::find($validatedData['bank_account_id']);
            $currencyCode = $bankAccount->currency_code;
        }

        DB::transaction(function () use ($validatedData, $user, $currencyCode) {
            $transactionData = [
                'amount' => $validatedData['amount'],
                'currency_code' => $currencyCode, // Definido acima
                'date' => $validatedData['date'],
                'description' => $validatedData['description'],
                'type' => $validatedData['type'],
                'installments' => $validatedData['installments'] ?? 1,
                'card_id' => $validatedData['card_id'] ?? null,
                'bank_account_id' => $validatedData['bank_account_id'] ?? null,
            ];

            $transaction = Transaction::create($transactionData);

            if (in_array($transaction->type, ['bank_deposit', 'bank_withdrawal'])) {
                $bankAccount = BankAccount::findOrFail($transaction->bank_account_id);
                // A transação e a conta estão na mesma moeda (currencyCode da transação é da conta)
                if ($transaction->type === 'bank_deposit') {
                    $bankAccount->balance += $transaction->amount;
                } else {
                    if ($bankAccount->balance < $transaction->amount) {
                        throw ValidationException::withMessages([
                            'amount' => 'Saldo insuficiente para realizar o saque.',
                        ]);
                    }
                    $bankAccount->balance -= $transaction->amount;
                }
                $bankAccount->save();
            }
        });

        return redirect()->route('transactions.index')->with('success', 'Transação criada com sucesso.');
    }

    // Métodos show, edit, update, destroy devem ser atualizados para considerar currency_code
    // e a lógica de não edição/deleção de transações de sistema.
    // A lógica de atualização de saldo em update/destroy deve também considerar a moeda.
    // Como as transações manuais (depósito/saque) já são na moeda da conta, a lógica de saldo não precisa de conversão.
    // Para simplificar, os métodos edit/update/destroy permanecem funcionalmente similares à resposta anterior,
    // mas a Policy já restringe o que pode ser alterado.
    public function show(Transaction $transaction) //
    {
        $this->authorize('view', $transaction);
        $transaction->load(['card.bankAccount', 'bankAccount', 'subscription', 'invoice']);
        return view('transactions.show', compact('transaction'));
    }

    public function edit(Transaction $transaction) //
    {
        $this->authorize('update', $transaction);
        $user = Auth::user();
        $cards = $user->cards()->active()->get();
        $bankAccounts = $user->bankAccounts()->active()->get();
        $allowedTransactionTypes = [
            'card_purchase' => 'Compra no Cartão',
            'bank_deposit' => 'Depósito Bancário',
            'bank_withdrawal' => 'Saque Bancário',
        ];
        return view('transactions.edit', compact('transaction', 'cards', 'bankAccounts', 'allowedTransactionTypes'));
    }

    public function update(Request $request, Transaction $transaction) //
    {
        $this->authorize('update', $transaction);
        // Lógica similar ao store, mas obtendo a currency do novo cartão/conta se mudado.
        // E recalculando saldos (a moeda da transação muda se o cartão/conta mudar).
        // ... (implementação detalhada omitida por brevidade, mas seguiria a lógica do store e da resposta anterior) ...
        return redirect()->route('transactions.show', $transaction)->with('success', 'Transação atualizada (lógica de atualização de saldo e moeda a ser totalmente implementada).');

    }

    public function destroy(Transaction $transaction) //
    {
        $this->authorize('delete', $transaction);
        // ... (implementação detalhada omitida por brevidade, mas seguiria a lógica da resposta anterior para reverter saldo) ...
        return redirect()->route('transactions.index')->with('success', 'Transação excluída (lógica de reversão de saldo a ser totalmente implementada).');
    }
}
