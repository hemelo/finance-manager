<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Invoice::class);
        $user = Auth::user();
        $cardIds = $user->cards()->pluck('id');
        $invoices = Invoice::whereIn('card_id', $cardIds)
            ->with('card')
            ->orderBy('due_date', 'desc')
            ->paginate(15); // Adicionando paginação
        return view('invoices.index', compact('invoices'));
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        $invoice->load('card.bankAccount', 'transactions', 'cashback');
        $bankAccounts = Auth::user()->bankAccounts;
        return view('invoices.show', compact('invoice', 'bankAccounts'));
    }

    public function pay(Request $request, Invoice $invoice)
    {
        $this->authorize('pay', $invoice); // Usando o método customizado da policy

        // A policy 'pay' já verifica se $invoice->status !== 'paid'
        // if ($invoice->status === 'paid') {
        //     return redirect()->route('invoices.show', $invoice)->with('warning', 'Esta fatura já foi paga.');
        // }

        $user = Auth::user();
        $validatedData = $request->validate([
            'payment_date' => 'required|date|before_or_equal:today',
            'bank_account_id' => 'required|exists:bank_accounts,id',
        ]);

        $bankAccount = BankAccount::where('id', $validatedData['bank_account_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($bankAccount->balance < $invoice->amount) {
            return redirect()->back()->withErrors(['bank_account_id' => 'Saldo insuficiente na conta bancária selecionada.'])->withInput();
        }

        DB::transaction(function () use ($invoice, $bankAccount, $validatedData) {
            $invoice->status = 'paid';
            $invoice->save();

            Transaction::create([
                'card_id' => $invoice->card_id,
                'bank_account_id' => $bankAccount->id,
                'invoice_id' => $invoice->id,
                'amount' => $invoice->amount,
                'date' => $validatedData['payment_date'],
                'description' => 'Pagamento Fatura ' . $invoice->card->name . ' - Ref: ' . $invoice->month_reference,
                'type' => 'invoice_payment',
                'installments' => 1,
            ]);

            $bankAccount->balance -= $invoice->amount;
            $bankAccount->save();
        });

        return redirect()->route('invoices.show', $invoice)->with('success', 'Fatura paga com sucesso!');
    }
}
