<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Notifications\InvoiceDueNotification;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendInvoiceDueNotifications extends Command
{
    protected $signature = 'app:invoices:notify-due';
    protected $description = 'Send notifications for invoices due within 3 days';

    public function handle(): void
    {
        $today = Carbon::today();
        $dueSoon = $today->addDays(3);

        $invoices = Invoice::where('status', 'open')
            ->whereBetween('due_date', [$today, $dueSoon])
            ->with('card.user')
            ->get();

        foreach ($invoices as $invoice) {
            $user = $invoice->card->user;
            $user->notify(new InvoiceDueNotification($invoice));
        }

        $this->info('Invoice due notifications sent successfully.');
    }
}
