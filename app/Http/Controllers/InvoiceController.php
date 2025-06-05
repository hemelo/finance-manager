<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\BankAccount;
use App\Services\CurrencyConverterService; // Adicionado
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; // Adicionado

class InvoiceController extends Controller
{
    protected CurrencyConverterService $currencyConverter; //

    public function __construct(CurrencyConverterService $currencyConverter) //
    {
        $this->currencyConverter = $currencyConverter;
    }

    public function index()
    {
        // ... (como na resposta anterior, mas as faturas agora têm currency_code)
        $this->authorize('viewAny', Invoice::class);
        $user = Auth::user();
        $cardIds = $user->cards()->pluck('id');
        $invoices = Invoice::whereIn('card_id', $cardIds)
            ->with('card')
            ->orderBy('due_date', 'desc')
            ->paginate(15);
        return view('invoices.index', compact('invoices'));
    }

    public function show(Invoice $invoice)
    {
        // ... (como na resposta anterior)
        $this->authorize('view', $invoice);
        $invoice->load('card.bankAccount', 'transactions', 'cashback');
        $bankAccounts = Auth::user()->bankAccounts()->active()->get(); // Apenas contas ativas para pagamento
        return view('invoices.show', compact('invoice', 'bankAccounts'));
    }

    public function pay(Request $request, Invoice $invoice)
    {
        $this->authorize('pay', $invoice);
        $user = Auth::user();
        $validatedData = $request->validate([
            'payment_date' => 'required|date|before_or_equal:today',
            'bank_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where('user_id', $user->id)->where('status', 'active')],
        ]);

        $bankAccount = BankAccount::findOrFail($validatedData['bank_account_id']);
        $paymentDate = Carbon::parse($validatedData['payment_date']);

        // Lógica de conversão de moeda
        $amountToDebit = $invoice->amount;
        $debitCurrency = $bankAccount->currency_code;

        if (strtoupper($invoice->currency_code) !== strtoupper($bankAccount->currency_code)) {
            $convertedAmount = $this->currencyConverter->convert(
                $invoice->amount,
                $invoice->currency_code,
                $bankAccount->currency_code,
                $paymentDate // Usa a data do pagamento para a taxa de câmbio
            );

            if ($convertedAmount === null) {
                return redirect()->back()->withErrors(['msg' => 'Não foi possível obter a taxa de conversão de moeda. Tente novamente mais tarde.'])->withInput();
            }
            $amountToDebit = round($convertedAmount, 2); // Arredondar para 2 casas decimais
        }

        if ($bankAccount->balance < $amountToDebit) {
            return redirect()->back()->withErrors(['bank_account_id' => "Saldo insuficiente ({$bankAccount->currency_code}). Necessário: {$amountToDebit}, Disponível: {$bankAccount->balance}"])->withInput();
        }

        DB::transaction(function () use ($invoice, $bankAccount, $validatedData, $amountToDebit, $paymentDate) {
            $invoice->status = 'paid';
            $invoice->save();

            Transaction::create([
                'card_id' => $invoice->card_id, // Transação é para o cartão da fatura
                'bank_account_id' => $bankAccount->id, // Dinheiro saiu desta conta
                'invoice_id' => $invoice->id,
                'amount' => $invoice->amount, // Valor original da fatura
                'currency_code' => $invoice->currency_code, // Moeda original da fatura
                'description' => 'Pagamento Fatura ' . $invoice->card->name . ' - Ref: ' . $invoice->month_reference . " (Debitado {$amountToDebit} {$bankAccount->currency_code})",
                'type' => 'invoice_payment',
                'date' => $paymentDate, //
                'installments' => 1,
            ]);

            $bankAccount->balance -= $amountToDebit;
            $bankAccount->save();
        });

        return redirect()->route('invoices.show', $invoice)->with('success', 'Fatura paga com sucesso!');
    }
}
