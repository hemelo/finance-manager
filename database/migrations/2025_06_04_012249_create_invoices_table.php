<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->onDelete('cascade');
            $table->string('month_reference'); // e.g., '2025-06'
            $table->decimal('amount', 10, 2);
            $table->decimal('cashback_rate', 5, 2)->nullable(); // e.g., 1.50 for 1.5%
            $table->date('due_date');
            $table->string('status')->default('open'); // e.g., 'open', 'paid'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
