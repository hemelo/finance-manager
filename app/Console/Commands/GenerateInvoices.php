<?php

namespace App\Console\Commands;

use App\Models\Card;
use App\Models\Cashback;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:invoices:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $cards = Card::all();
        $today = Carbon::today();

        foreach ($cards as $card) {
            $dueDate = Carbon::parse($card->due_date);
            if ($today->day === $dueDate->day) {
                $transactions = $card->transactions()
                    ->whereBetween('date', [$today->startOfMonth(), $today->endOfMonth()])
                    ->whereDoesntHave('invoices')
                    ->get();

                $totalAmount = $transactions->sum('amount');

                // Use a default cashback rate or allow user input (e.g., from a form)
                $cashbackRate = $card->cashback_rate ?? 1.0; // Fallback to 1%

                $invoice = Invoice::create([
                    'card_id' => $card->id,
                    'month_reference' => $today->format('Y-m'),
                    'amount' => $totalAmount,
                    'cashback_rate' => $cashbackRate,
                    'due_date' => $today->copy()->addDays(10),
                    'status' => 'open',
                ]);

                // Calculate and store cashback
                $cashbackAmount = $invoice->calculateCashback();
                Cashback::create([
                    'card_id' => $card->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $cashbackAmount,
                    'calculation_date' => $today,
                ]);

                // Link transactions (including subscription transactions) to the invoice
                $transactions->each->update(['invoice_id' => $invoice->id]);
            }
        }

        $this->info('Invoices generated successfully.');
    }
}
