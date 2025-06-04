<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\SubscriptionDueNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendSubscriptionDueNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:subscriptions:send-due-notifications';

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
        $today = Carbon::today();
        $dueSoon = $today->addDays(3);

        $subscriptions = Subscription::where('status', 'active')
            ->whereBetween('next_billing_date', [$today, $dueSoon])
            ->with('card.user')
            ->get();

        foreach ($subscriptions as $subscription) {
            $user = $subscription->card->user;
            $user->notify(new SubscriptionDueNotification($subscription));
        }

        $this->info('Subscription due notifications sent successfully.');
    }
}
