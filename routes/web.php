<?php

use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::resource('cards', CardController::class);
    Route::resource('transactions', TransactionController::class);
    Route::resource('invoices', InvoiceController::class);
    Route::resource('bank_accounts', BankAccountController::class);
    Route::resource('transactions', TransactionController::class);
});
