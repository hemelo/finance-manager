<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency_code', 3);
            $table->string('to_currency_code', 3);
            $table->decimal('rate', 15, 8);
            $table->date('date');
            $table->timestamps();
            $table->unique(['from_currency_code', 'to_currency_code', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
