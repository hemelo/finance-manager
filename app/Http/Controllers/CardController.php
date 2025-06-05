<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CardController extends Controller
{
    // ... index(), create() (no create, passar closing_day para o form se necessário)

    public function store(Request $request)
    {
        $this->authorize('create', Card::class);
        $user = Auth::user();
        $validatedData = $request->validate([
            'bank_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where('user_id', $user->id)->where('status', 'active')],
            'name' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
            'currency_code' => 'required|string|size:3',
            'limit' => 'required|numeric|min:0',
            'closing_day' => 'required|integer|min:1|max:28', // Validação para closing_day
            'due_date' => 'required|date_format:Y-m-d', // Dia do pagamento da fatura
            'cashback_rate' => 'nullable|numeric|min:0|max:100',
            'is_cashback_per_transaction' => 'sometimes|boolean',
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $card = new Card($validatedData);
        $card->user_id = $user->id;
        // 'currency_code', 'closing_day' já estão em $validatedData
        $card->currency_code = strtoupper($validatedData['currency_code']);
        $card->is_cashback_per_transaction = $request->boolean('is_cashback_per_transaction');
        $card->status = $request->status ?? 'active';
        $card->save();

        return redirect()->route('cards.index')->with('success', 'Cartão criado com sucesso.');
    }

    // ... show(), edit() (no edit, passar closing_day para o form)

    public function update(Request $request, Card $card)
    {
        $this->authorize('update', $card);
        // A conta bancária e a moeda do cartão não podem ser alteradas.
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
            'limit' => 'required|numeric|min:0',
            'closing_day' => 'required|integer|min:1|max:28', // Validação para closing_day
            'due_date' => 'required|date_format:Y-m-d',
            'cashback_rate' => 'nullable|numeric|min:0|max:100',
            'is_cashback_per_transaction' => 'sometimes|boolean',
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if ($validatedData['status'] === 'inactive' && $card->subscriptions()->where('status', 'active')->exists()) {
            return redirect()->back()->withErrors(['status' => 'Não é possível desativar o cartão. Existem assinaturas ativas vinculadas a ele.'])->withInput();
        }

        $card->fill($validatedData); // closing_day será preenchido
        $card->is_cashback_per_transaction = $request->boolean('is_cashback_per_transaction');
        $card->save();

        return redirect()->route('cards.show', $card)->with('success', 'Cartão atualizado com sucesso.');
    }

    // ... toggleStatus() (sem alteração direta para closing_day aqui)
    public function index() // Copiado da resposta anterior para integridade do arquivo
    {
        $this->authorize('viewAny', Card::class);
        $user = Auth::user();
        $cards = $user->cards()->with('bankAccount')->orderBy('status')->orderBy('name')->get();
        return view('cards.index', compact('cards'));
    }

    public function create() // Copiado da resposta anterior para integridade do arquivo
    {
        $this->authorize('create', Card::class);
        $user = Auth::user();
        $bankAccounts = $user->bankAccounts()->active()->get();
        if ($bankAccounts->isEmpty()) {
            return redirect()->route('bank_accounts.create')->with('warning', 'Você precisa ter uma conta bancária ativa antes de adicionar um cartão.');
        }
        return view('cards.create', compact('bankAccounts'));
    }
    public function show(Card $card) // Copiado da resposta anterior para integridade do arquivo
    {
        $this->authorize('view', $card);
        $card->load([
            'transactions' => fn($q) => $q->orderBy('date', 'desc')->limit(10),
            'invoices' => fn($q) => $q->orderBy('month_reference', 'desc'),
            'bankAccount',
            'subscriptions' => fn($q) => $q->whereIn('status', ['active', 'paused'])
        ]);
        return view('cards.show', compact('card'));
    }

    public function edit(Card $card) // Copiado da resposta anterior para integridade do arquivo
    {
        $this->authorize('update', $card);
        $user = Auth::user();
        $bankAccounts = $user->bankAccounts()->active()->get();
        return view('cards.edit', compact('card', 'bankAccounts'));
    }
    public function toggleStatus(Card $card) // Copiado da resposta anterior para integridade do arquivo
    {
        $this->authorize('toggleStatus', $card);

        if ($card->status === 'inactive') {
            if ($card->bankAccount && $card->bankAccount->status !== 'active') {
                return redirect()->route('cards.show', $card)->with('error', 'Não é possível ativar o cartão. A conta bancária associada está inativa.');
            }
            $card->status = 'active';
            $message = 'Cartão ativado com sucesso.';
        } else {
            if ($card->subscriptions()->where('status', 'active')->exists()) {
                return redirect()->route('cards.show', $card)->with('error', 'Não é possível desativar o cartão. Existem assinaturas ativas vinculadas a ele.');
            }
            $card->status = 'inactive';
            $message = 'Cartão desativado com sucesso.';
        }
        $card->save();

        return redirect()->route('cards.index')->with('success', $message);
    }
}
