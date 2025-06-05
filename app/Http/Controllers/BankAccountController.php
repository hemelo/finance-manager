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
        $bankAccounts = Auth::user()->bankAccounts()->orderBy('status')->orderBy('bank_name')->get();
        return view('bank_accounts.index', compact('bankAccounts'));
    }

    public function create()
    {
        $this->authorize('create', BankAccount::class);
        // Você pode querer passar uma lista de códigos de moeda válidos para a view
        // $currencyCodes = ['USD', 'BRL', 'EUR', ...];
        return view('bank_accounts.create'/*, compact('currencyCodes')*/);
    }

    public function store(Request $request)
    {
        $this->authorize('create', BankAccount::class);
        $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_number' => [
                'required', 'string', 'max:255',
                Rule::unique('bank_accounts')->where('user_id', Auth::id()) // Correção: Único por usuário, não global
            ],
            'currency_code' => 'required|string|size:3', // Validar contra lista de moedas suportadas se necessário
            'balance' => 'required|numeric|min:0',
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        BankAccount::create([
            'user_id' => Auth::id(),
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'currency_code' => strtoupper($request->currency_code),
            'balance' => $request->balance,
            'status' => $request->status ?? 'active',
        ]);

        return redirect()->route('bank_accounts.index')->with('success', 'Conta bancária criada com sucesso.');
    }

    public function show(BankAccount $bankAccount)
    {
        $this->authorize('view', $bankAccount);
        $bankAccount->load(['transactions' => function ($query) {
            $query->orderBy('date', 'desc');
        }, 'cards' => function ($query) { // Carregar cartões ativos associados
            $query->active();
        }]);
        return view('bank_accounts.show', compact('bankAccount'));
    }

    public function edit(BankAccount $bankAccount)
    {
        $this->authorize('update', $bankAccount);
        // A moeda não deve ser editável após a criação
        return view('bank_accounts.edit', compact('bankAccount'));
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        $this->authorize('update', $bankAccount);

        $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_number' => [
                'required', 'string', 'max:255',
                Rule::unique('bank_accounts')->where('user_id', Auth::id())->ignore($bankAccount->id) // Correção
            ],
            // 'currency_code' não deve ser editável
            // 'balance' é atualizado via transações
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $bankAccount->update($request->only(['bank_name', 'account_number', 'status']));

        return redirect()->route('bank_accounts.show', $bankAccount)->with('success', 'Conta bancária atualizada com sucesso.');
    }

    // Remover destroy e adicionar toggleStatus
    public function toggleStatus(BankAccount $bankAccount)
    {
        $this->authorize('toggleStatus', $bankAccount); // Usando a nova permissão da policy

        if ($bankAccount->status === 'inactive') {
            $bankAccount->status = 'active';
            $message = 'Conta bancária ativada com sucesso.';
        } else {
            // Lógica para impedir desativação se houver cartões ativos vinculados
            if ($bankAccount->cards()->active()->exists()) {
                return redirect()->route('bank_accounts.show', $bankAccount)->with('error', 'Não é possível desativar. Existem cartões ativos vinculados a esta conta.');
            }
            $bankAccount->status = 'inactive';
            $message = 'Conta bancária desativada com sucesso.';
        }
        $bankAccount->save();

        return redirect()->route('bank_accounts.index')->with('success', $message);
    }
}
