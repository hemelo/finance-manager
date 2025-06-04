<?php

namespace App\Providers;

use App\Models\BankAccount;
use App\Models\Card;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Policies\BankAccountPolicy;
use App\Policies\CardPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\TransactionPolicy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Subscription::class => SubscriptionPolicy::class,
        BankAccount::class => BankAccountPolicy::class,
        Card::class => CardPolicy::class,
        Invoice::class => InvoicePolicy::class,
        Transaction::class => TransactionPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

    }
}
