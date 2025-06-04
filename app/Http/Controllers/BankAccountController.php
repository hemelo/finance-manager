<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function index()
    {
        $bankAccounts = auth()->user()->bankAccounts;
        return view('bank_accounts.index', compact('bankAccounts'));
    }

    public function create()
    {
        return view('bank_accounts.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255|unique:bank_accounts',
            'balance' => 'required|numeric|min:0',
        ]);

        BankAccount::create([
            'user_id' => auth()->id(),
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'balance' => $request->balance,
        ]);

        return redirect()->route('bank_accounts.index')->with('success', 'Bank account created successfully.');
    }

    public function show(BankAccount $bankAccount)
    {
        $this->authorize('view', $bankAccount);
        return view('bank_accounts.show', compact('bankAccount'));
    }
}
