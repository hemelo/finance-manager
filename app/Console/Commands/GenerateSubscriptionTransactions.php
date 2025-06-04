<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateSubscriptionTransactions extends Command
{
    protected $signature = 'app:subscriptions:generate-transactions';
    protected $description = 'Generate transactions for active subscriptions';

    public function handle(): void
    {
        $today = Carbon::today();
        $subscriptions = Subscription::where('status', 'active')
            ->where('next_billing_date', '<=', $today)
            ->get();

        foreach ($subscriptions as $subscription) {
            // Create transaction
            $transaction = Transaction::create([
                'card_id' => $subscription->card_id,
                'subscription_id' => $subscription->id,
                'amount' => $subscription->amount,
                'date' => $today,
                'description' => $subscription->name . ' (Subscription)',
                'type' => 'purchase',
                'installments' => 1,
            ]);

            // Update next billing date
            $nextBillingDate = $this->calculateNextBillingDate($subscription);
            $subscription->update(['next_billing_date' => $nextBillingDate]);

            $this->info("Transaction created for subscription: {$subscription->name}");
        }

        $this->info('Subscription transactions generated successfully.');
    }

    protected function calculateNextBillingDate(Subscription $subscription): Carbon
    {
        $currentBillingDate = Carbon::parse($subscription->next_billing_date);
        if ($subscription->frequency === 'monthly') {
            return $currentBillingDate->addMonth();
        } elseif ($subscription->frequency === 'yearly') {
            return $currentBillingDate->addYear();
        }
        return $currentBillingDate->addMonth(); // Default to monthly
    }
}
