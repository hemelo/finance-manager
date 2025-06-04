<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Card;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
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
                // Se houver card_ids, e queremos transações de contas bancárias que NÃO são de cartões (pagamentos, depósitos etc)
                // precisamos ser cuidadosos para não duplicar ou excluir indevidamente.
                // Se uma transação tem card_id E bank_account_id (ex: pagamento de fatura), já foi pega pelo card_id.
                // Esta lógica assume que uma transação ou é de cartão OU é de conta bancária (diretamente).
                $q->orWhereIn('bank_account_id', $bankAccountIds);
            }
        });

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('month')) {
            try {
                $date = \Carbon\Carbon::createFromFormat('Y-m', $request->month);
                $query->whereYear('date', $date->year)->whereMonth('date', $date->month);
            } catch (\Exception $e) {
                // Ignorar filtro de mês inválido ou adicionar erro
            }
        }

        $transactions = $query->with(['card.bankAccount', 'bankAccount', 'subscription', 'invoice'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $transactionTypes = [ // Para popular um dropdown de filtro, por exemplo
            'card_purchase' => 'Compra no Cartão',
            'bank_deposit' => 'Depósito Bancário',
            'bank_withdrawal' => 'Saque Bancário',
            'invoice_payment' => 'Pagamento de Fatura',
            // Adicionar outros tipos de transação de subscriptions se necessário um filtro específico
        ];

        return view('transactions.index', compact('transactions', 'transactionTypes'));
    }

    public function create()
    {
        $this->authorize('create', Transaction::class);
        $user = Auth::user();
        $cards = $user->cards;
        $bankAccounts = $user->bankAccounts;

        if ($cards->isEmpty() && $bankAccounts->isEmpty()) {
            return redirect()->route('dashboard')
                ->with('warning', 'Você precisa cadastrar um cartão ou conta bancária antes de adicionar uma transação.');
        }
        // Tipos de transação permitidos para criação manual
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

        // Tipos de transação permitidos na criação manual via este formulário
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
                Rule::exists('cards', 'id')->where('user_id', $user->id),
            ],
            'bank_account_id' => [
                'nullable',
                Rule::requiredIf(fn () => in_array($request->type, ['bank_deposit', 'bank_withdrawal'])),
                Rule::exists('bank_accounts', 'id')->where('user_id', $user->id),
            ],
        ]);

        DB::transaction(function () use ($validatedData, $user) {
            $transactionData = [
                'amount' => $validatedData['amount'],
                'date' => $validatedData['date'],
                'description' => $validatedData['description'],
                'type' => $validatedData['type'],
                'installments' => $validatedData['installments'] ?? 1,
                'card_id' => $validatedData['card_id'] ?? null,
                'bank_account_id' => $validatedData['bank_account_id'] ?? null,
            ];

            // Validações de pertencimento já estão no Rule::exists com where('user_id', ...)

            $transaction = Transaction::create($transactionData);

            if (in_array($transaction->type, ['bank_deposit', 'bank_withdrawal'])) {
                $bankAccount = BankAccount::findOrFail($transaction->bank_account_id); // findOrFail para garantir que existe
                if ($transaction->type === 'bank_deposit') {
                    $bankAccount->balance += $transaction->amount;
                } else { // bank_withdrawal
                    if ($bankAccount->balance < $transaction->amount) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
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

    public function show(Transaction $transaction)
    {
        $this->authorize('view', $transaction);
        $transaction->load(['card.bankAccount', 'bankAccount', 'subscription', 'invoice']);
        return view('transactions.show', compact('transaction'));
    }

    public function edit(Transaction $transaction)
    {
        $this->authorize('update', $transaction); // A policy já verifica se é editável
        $user = Auth::user();
        $cards = $user->cards;
        $bankAccounts = $user->bankAccounts;
        $allowedTransactionTypes = [ // Tipos permitidos para edição
            'card_purchase' => 'Compra no Cartão',
            'bank_deposit' => 'Depósito Bancário',
            'bank_withdrawal' => 'Saque Bancário',
        ];
        return view('transactions.edit', compact('transaction', 'cards', 'bankAccounts', 'allowedTransactionTypes'));
    }

    public function update(Request $request, Transaction $transaction)
    {
        $this->authorize('update', $transaction);  // A policy já verifica se é atualizável
        $user = Auth::user();

        $oldAmount = $transaction->amount;
        $oldType = $transaction->type;
        $oldBankAccountId = $transaction->bank_account_id;
        $oldCardId = $transaction->card_id; //

        $editableTypes = ['card_purchase', 'bank_deposit', 'bank_withdrawal'];
        $validatedData = $request->validate([
            'type' => ['required', Rule::in($editableTypes)],
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date|before_or_equal:today',
            'description' => 'required|string|max:255',
            'installments' => 'nullable|integer|min:1',
            'card_id' => [
                'nullable',
                Rule::requiredIf(fn () => $request->type === 'card_purchase'),
                Rule::exists('cards', 'id')->where('user_id', $user->id),
            ],
            'bank_account_id' => [
                'nullable',
                Rule::requiredIf(fn () => in_array($request->type, ['bank_deposit', 'bank_withdrawal'])),
                Rule::exists('bank_accounts', 'id')->where('user_id', $user->id),
            ],
        ]);

        DB::transaction(function () use ($transaction, $validatedData, $oldAmount, $oldType, $oldBankAccountId, $oldCardId, $user) {
            // Reverter impacto da transação antiga no saldo da conta (se aplicável)
            if ($oldBankAccountId && in_array($oldType, ['bank_deposit', 'bank_withdrawal'])) {
                $oldBankAccount = BankAccount::where('id',$oldBankAccountId)->where('user_id', $user->id)->first();
                if ($oldBankAccount) {
                    if ($oldType === 'bank_deposit') $oldBankAccount->balance -= $oldAmount;
                    else $oldBankAccount->balance += $oldAmount; // bank_withdrawal
                    $oldBankAccount->save();
                }
            }

            $updateData = [
                'amount' => $validatedData['amount'],
                'date' => $validatedData['date'],
                'description' => $validatedData['description'],
                'type' => $validatedData['type'],
                'installments' => $validatedData['installments'] ?? 1,
                'card_id' => $validatedData['card_id'] ?? null,
                'bank_account_id' => $validatedData['bank_account_id'] ?? null,
            ];
            // Se o tipo mudou de/para card_purchase, zerar o outro ID
            if ($updateData['type'] === 'card_purchase') $updateData['bank_account_id'] = null;
            if (in_array($updateData['type'], ['bank_deposit', 'bank_withdrawal'])) $updateData['card_id'] = null;


            $transaction->update($updateData);

            // Aplicar impacto da nova transação no saldo da conta (se aplicável)
            if ($transaction->bank_account_id && in_array($transaction->type, ['bank_deposit', 'bank_withdrawal'])) {
                $currentBankAccount = BankAccount::where('id', $transaction->bank_account_id)->where('user_id', $user->id)->firstOrFail();
                if ($transaction->type === 'bank_deposit') {
                    $currentBankAccount->balance += $transaction->amount;
                } else { // bank_withdrawal
                    if ($currentBankAccount->balance < $transaction->amount) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'amount' => 'Saldo insuficiente para realizar o saque com o novo valor/conta.',
                        ]);
                    }
                    $currentBankAccount->balance -= $transaction->amount;
                }
                $currentBankAccount->save();
            }
        });

        return redirect()->route('transactions.show', $transaction)->with('success', 'Transação atualizada com sucesso.');
    }

    public function destroy(Transaction $transaction)
    {
        $this->authorize('delete', $transaction); // A policy já verifica se é deletável

        DB::transaction(function () use ($transaction) {
            if ($transaction->bank_account_id && in_array($transaction->type, ['bank_deposit', 'bank_withdrawal'])) {
                $bankAccount = BankAccount::where('id', $transaction->bank_account_id)->where('user_id', Auth::id())->first();
                if ($bankAccount) {
                    if ($transaction->type === 'bank_deposit') $bankAccount->balance -= $transaction->amount;
                    else $bankAccount->balance += $transaction->amount; // bank_withdrawal
                    $bankAccount->save();
                }
            }
            $transaction->delete();
        });

        return redirect()->route('transactions.index')->with('success', 'Transação excluída com sucesso.');
    }
}
