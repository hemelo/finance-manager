<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CardController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Card::class);
        $user = Auth::user();
        $cards = $user->cards()->with('bankAccount')->get();
        return view('cards.index', compact('cards'));
    }

    public function create()
    {
        $this->authorize('create', Card::class);
        $user = Auth::user();
        $bankAccounts = $user->bankAccounts;
        if ($bankAccounts->isEmpty()) {
            return redirect()->route('bank_accounts.create')->with('warning', 'Você precisa cadastrar uma conta bancária antes de adicionar um cartão.');
        }
        return view('cards.create', compact('bankAccounts'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Card::class);
        $user = Auth::user();
        $validatedData = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'name' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
            'limit' => 'required|numeric|min:0',
            'due_date' => 'required|date_format:Y-m-d', // Mantido como data completa conforme migration
            'cashback_rate' => 'nullable|numeric|min:0|max:100',
            'is_cashback_per_transaction' => 'sometimes|boolean',
        ]);

        // Assegurar que a bank_account_id pertence ao usuário autenticado
        $bankAccount = BankAccount::where('id', $validatedData['bank_account_id'])
            ->where('user_id', $user->id)
            ->firstOrFail(); // Isso já lança 404 se não encontrar/pertencer

        $card = new Card($validatedData);
        $card->user_id = $user->id;
        // $card->bank_account_id = $bankAccount->id; // bank_account_id já está em $validatedData
        $card->is_cashback_per_transaction = $request->boolean('is_cashback_per_transaction'); // Melhor forma de pegar boolean
        $card->save();

        return redirect()->route('cards.index')->with('success', 'Cartão criado com sucesso.');
    }

    public function show(Card $card)
    {
        $this->authorize('view', $card);
        $card->load(['transactions' => function ($query) {
            $query->orderBy('date', 'desc')->limit(10);
        }, 'invoices' => function ($query) {
            $query->orderBy('month_reference', 'desc');
        }, 'bankAccount']);
        return view('cards.show', compact('card'));
    }

    public function edit(Card $card)
    {
        $this->authorize('update', $card);
        $user = Auth::user();
        $bankAccounts = $user->bankAccounts;
        return view('cards.edit', compact('card', 'bankAccounts'));
    }

    public function update(Request $request, Card $card)
    {
        $this->authorize('update', $card);
        $user = Auth::user();
        $validatedData = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'name' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
            'limit' => 'required|numeric|min:0',
            'due_date' => 'required|date_format:Y-m-d',
            'cashback_rate' => 'nullable|numeric|min:0|max:100',
            'is_cashback_per_transaction' => 'sometimes|boolean',
        ]);

        BankAccount::where('id', $validatedData['bank_account_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        $card->fill($validatedData);
        $card->is_cashback_per_transaction = $request->boolean('is_cashback_per_transaction');
        $card->save();

        return redirect()->route('cards.show', $card)->with('success', 'Cartão atualizado com sucesso.');
    }

    public function destroy(Card $card)
    {
        $this->authorize('delete', $card);
        $card->delete();
        return redirect()->route('cards.index')->with('success', 'Cartão excluído com sucesso.');
    }
}
