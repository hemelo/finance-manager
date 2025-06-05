<?php

namespace App\Console\Commands;

use App\Models\Card;
use App\Models\Cashback;
use App\Models\Invoice;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateInvoices extends Command
{
    protected $signature = 'app:invoices:generate {--force : Force generation for all cards and relevant past closing dates} {--date= : Run as if it is this date (YYYY-MM-DD)}';

    protected $description = 'Generates invoices for active cards based on their closing day.';

    public function handle(): void
    {
        $this->info('Starting invoice generation process...');
        $runDate = $this->option('date') ? Carbon::parse($this->option('date'))->startOfDay() : Carbon::today();
        $this->info("Running for date: {$runDate->toDateString()}");

        $activeCards = Card::where('status', 'active')
            ->with('user')
            ->get();

        if ($activeCards->isEmpty()) {
            $this->info('No active cards found to process.');
            return;
        }

        foreach ($activeCards as $card) {
            $this->processCardInvoice($card, $runDate);
        }

        $this->info('Invoice generation process finished successfully.');
    }

    protected function processCardInvoice(Card $card, Carbon $runDate): void
    {
        $this->info("Processing Card: {$card->name} (ID: {$card->id}) for User: {$card->user->name} - Closing Day: {$card->closing_day}, Due Day (from card):  {$card->payment_due_day}");

        // Determinar a data de fechamento relevante para este cartão com base no $runDate
        // A data de fechamento é $card->closing_day do mês atual ou do mês anterior.

        $currentMonthClosingDate = Carbon::create($runDate->year, $runDate->month, $card->closing_day, 0, 0, 0);
        if ($currentMonthClosingDate->day !== $card->closing_day) { // Caso closing_day = 31 e mês tenha 30 dias
            $currentMonthClosingDate->endOfMonth()->startOfDay(); // Usa o último dia do mês
        }


        $relevantClosingDate = null;

        if ($runDate->gte($currentMonthClosingDate)) {
            // Se a data de execução é igual ou posterior ao dia de fechamento deste mês,
            // então o fechamento relevante é $currentMonthClosingDate.
            $relevantClosingDate = $currentMonthClosingDate;
        } else {
            // Se a data de execução é ANTERIOR ao dia de fechamento deste mês,
            // então o fechamento relevante foi no mês passado.
            $relevantClosingDate = $currentMonthClosingDate->copy()->subMonthNoOverflow();
            // Ajustar para o dia de fechamento correto no mês anterior
            $targetDay = $card->closing_day;
            $daysInPreviousMonth = $relevantClosingDate->daysInMonth;
            if ($targetDay > $daysInPreviousMonth) $targetDay = $daysInPreviousMonth;
            $relevantClosingDate->setDay($targetDay);

        }

        // Se forçado, processa o último fechamento relevante, mesmo que já tenha passado um pouco.
        // Sem --force, só processa se $runDate for o dia de fechamento ou um dia depois (para pegar atrasos)
        if (!$this->option('force') && !$runDate->isSameDay($relevantClosingDate) && !$runDate->isSameDay($relevantClosingDate->copy()->addDay())) {
            $this->line("Skipping card ID {$card->id}. Today ({$runDate->toDateString()}) is not its closing day ({$relevantClosingDate->toDateString()}) or the day after.");
            //return; // Com a lógica de idempotência, podemos permitir que rode e ele pulará se já existir
        }


        $monthReference = $relevantClosingDate->format('Y-m');

        // 1. Idempotency Check
        if (Invoice::where('card_id', $card->id)->where('month_reference', $monthReference)->exists()) {
            $this->warn("Invoice already exists for card ID {$card->id} for month reference {$monthReference}. Skipping.");
            return;
        }

        // 2. Definir Período de Transações
        $invoiceEndDate = $relevantClosingDate->copy()->endOfDay(); // Até o final do dia de fechamento

        $previousClosingDate = $relevantClosingDate->copy()->subMonthNoOverflow();
        $daysInPrevClosingMonth = $previousClosingDate->daysInMonth;
        $targetClosingDayPrev = $card->closing_day;
        if ($targetClosingDayPrev > $daysInPrevClosingMonth) $targetClosingDayPrev = $daysInPrevClosingMonth;
        $previousClosingDate->setDay($targetClosingDayPrev);

        $invoiceStartDate = $previousClosingDate->copy()->addDay()->startOfDay(); // Dia seguinte ao fechamento anterior

        $this->line("Invoice period for card ID {$card->id}: {$invoiceStartDate->toDateTimeString()} to {$invoiceEndDate->toDateTimeString()} for month_ref {$monthReference}");

        // 3. Fetch Unbilled Transactions
        $transactionsToInvoice = $card->transactions()
            ->where('currency_code', $card->currency_code)
            ->whereBetween('date', [$invoiceStartDate, $invoiceEndDate])
            ->whereNull('invoice_id')
            ->get();

        if ($transactionsToInvoice->isEmpty()) {
            $this->line("No unbilled transactions for card ID {$card->id} in this period. Skipping.");
            return;
        }

        $totalAmount = $transactionsToInvoice->sum('amount');
        if ($totalAmount <= 0 && !$this->option('force')) { // Permitir forçar fatura zerada
            $this->line("Total transaction amount for card ID {$card->id} is {$totalAmount} {$card->currency_code}. No invoice generated unless forced.");
            return;
        }

        // 4. Determine Invoice Due Date
        $cardPaymentDueDay = $card->payment_due_day; // Dia do pagamento do cartão

        // A fatura que fecha em $relevantClosingDate (ex: 20/Julho) vence no $cardPaymentDueDay do mês seguinte (ex: 10/Agosto)
        $invoiceDueDate = $relevantClosingDate->copy()->addMonthNoOverflow()->setDay($cardPaymentDueDay);
        if ($invoiceDueDate->day !== $cardPaymentDueDay) { // Se setDay ajustou para último dia do mês
            $invoiceDueDate->endOfMonth()->startOfDay();
        }


        // 5. Create Invoice and Cashback
        try {
            DB::transaction(function () use ($card, $monthReference, $totalAmount, $invoiceDueDate, $transactionsToInvoice, $relevantClosingDate) {
                $invoice = Invoice::create([
                    'card_id' => $card->id,
                    'month_reference' => $monthReference, // Mês do fechamento
                    'amount' => $totalAmount,
                    'currency_code' => $card->currency_code,
                    'cashback_rate' => $card->cashback_rate,
                    'due_date' => $invoiceDueDate,
                    'status' => 'open',
                ]);

                $transactionIdsToUpdate = $transactionsToInvoice->pluck('id');
                Transaction::whereIn('id', $transactionIdsToUpdate)->update(['invoice_id' => $invoice->id]);
                $this->line("Invoice ID {$invoice->id} created. Amount: {$invoice->amount} {$invoice->currency_code}. Due: {$invoice->due_date->toDateString()}.");

                if ($card->cashback_rate && $card->cashback_rate > 0) {
                    $calculatedCashbackAmount = 0;
                    if ($card->is_cashback_per_transaction) {
                        foreach ($transactionsToInvoice as $transaction) {
                            if (in_array($transaction->type, ['card_purchase', 'subscription_purchase'])) {
                                $calculatedCashbackAmount += ($transaction->amount * $card->cashback_rate) / 100;
                            }
                        }
                    } else {
                        $calculatedCashbackAmount = ($totalAmount * $card->cashback_rate) / 100;
                    }
                    $finalCashbackAmount = round($calculatedCashbackAmount, 2);
                    if ($finalCashbackAmount > 0) {
                        Cashback::create([
                            'card_id' => $card->id,
                            'transaction_id' => null,
                            'invoice_id' => $invoice->id,
                            'amount' => $finalCashbackAmount,
                            'currency_code' => $card->currency_code,
                            'calculation_date' => $relevantClosingDate, // Data do fechamento
                        ]);
                        $this->line("Cashback of {$finalCashbackAmount} {$card->currency_code} for invoice ID {$invoice->id}.");
                    }
                }
            });
        } catch (\Exception $e) {
            $this->error("Failed for card ID {$card->id} / {$monthReference}: " . $e->getMessage());
            // Considerar logar $e->getTraceAsString() para debug completo
        }
    }
}
