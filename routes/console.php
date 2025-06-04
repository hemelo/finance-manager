<?php

use App\Console\Commands\GenerateInvoices;
use App\Console\Commands\GenerateSubscriptionTransactions;
use App\Console\Commands\SendInvoiceDueNotifications;
use App\Console\Commands\SendSubscriptionDueNotifications;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(GenerateInvoices::class)->daily();
Schedule::call(SendInvoiceDueNotifications::class)->dailyAt("08:00");
Schedule::call(GenerateSubscriptionTransactions::class)->daily();
Schedule::call(SendSubscriptionDueNotifications::class)->dailyAt("09:00");
