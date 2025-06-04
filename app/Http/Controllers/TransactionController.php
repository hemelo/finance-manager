<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Card;
use App\Models\BankAccount;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = auth()->user()->bankAccounts()->with('transactions')->get()->pluck('transactions')->flatten();
        return view('transactions.index', compact('transactions'));
    }

    public function create()
    {
        $cards = auth()->user()->cards;
        $bankAccounts = auth()->user()->bankAccounts;
        return view('transactions.create', compact('cards', 'bankAccounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:card_purchase,bank_deposit,bank_withdrawal',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'description' => 'required|string',
            'card_id' => 'required_if:type,card_purchase|exists:cards,id',
            'bank_account_id' => 'required_if:type,bank_deposit,bank_withdrawal|exists:bank_accounts,id',
        ]);

        $transaction = Transaction::create($request->only([
            'type', 'amount', 'date', 'description', 'card_id', 'bank_account_id'
        ]));

        if (in_array($request->type, ['bank_deposit', 'bank_withdrawal'])) {
            $bankAccount = BankAccount::find($request->bank_account_id);
            if ($request->type === 'bank_deposit') {
                $bankAccount->balance += $request->amount;
            } else {
                $bankAccount->balance -= $request->amount;
            }
            $bankAccount->save();
        }

        return redirect()->route('transactions.index')->with('success', 'Transaction created successfully.');
    }
}
