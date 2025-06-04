<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BankAccountController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', BankAccount::class);
        $bankAccounts = Auth::user()->bankAccounts()->get(); // Otimizado
        return view('bank_accounts.index', compact('bankAccounts'));
    }

    public function create()
    {
        $this->authorize('create', BankAccount::class);
        return view('bank_accounts.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', BankAccount::class);
        $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_number' => [
                'required',
                'string',
                'max:255',
                // A migration define unique globalmente para account_number
                // Se fosse único por usuário, seria: Rule::unique('bank_accounts')->where('user_id', Auth::id())
                Rule::unique('bank_accounts'),
            ],
            'balance' => 'required|numeric|min:0',
        ]);

        BankAccount::create([
            'user_id' => Auth::id(),
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'balance' => $request->balance,
        ]);

        return redirect()->route('bank_accounts.index')->with('success', 'Conta bancária criada com sucesso.');
    }

    public function show(BankAccount $bankAccount)
    {
        $this->authorize('view', $bankAccount);
        $bankAccount->load(['transactions' => function ($query) {
            $query->orderBy('date', 'desc');
        }]);
        return view('bank_accounts.show', compact('bankAccount'));
    }

    public function edit(BankAccount $bankAccount)
    {
        $this->authorize('update', $bankAccount);
        return view('bank_accounts.edit', compact('bankAccount'));
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        $this->authorize('update', $bankAccount);

        $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('bank_accounts')->ignore($bankAccount->id),
            ],
        ]);

        $bankAccount->update($request->only(['bank_name', 'account_number']));

        return redirect()->route('bank_accounts.show', $bankAccount)->with('success', 'Conta bancária atualizada com sucesso.');
    }

    public function destroy(BankAccount $bankAccount)
    {
        $this->authorize('delete', $bankAccount);

        // Verifica se a conta bancária tem cartões vinculados
        if ($bankAccount->cards()->exists()) {
            return redirect()->route('bank_accounts.index')->with('error', 'Não é possível excluir a conta. Existem cartões vinculados a ela. Remova ou reatribua os cartões primeiro.');
        }
        // Verifica se a conta bancária tem saldo diferente de zero
        if ($bankAccount->balance != 0) {
            return redirect()->route('bank_accounts.show', $bankAccount)->with('error', 'Não é possível excluir a conta com saldo diferente de zero.');
        }

        // Verifica se a conta bancária tem transações sem cartão associado
        if ($bankAccount->transactions()->whereDoesntHave('card')->exists()){
            return redirect()->route('bank_accounts.show', $bankAccount)->with('error', 'Não é possível excluir a conta pois existem transações bancárias associadas.');
        }

        $bankAccount->delete();
        return redirect()->route('bank_accounts.index')->with('success', 'Conta bancária excluída com sucesso.');
    }
}
