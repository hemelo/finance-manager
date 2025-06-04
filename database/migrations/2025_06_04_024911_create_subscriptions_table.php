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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('card_id')->constrained()->onDelete('cascade');
            $table->string('name'); // e.g., "Netflix Subscription"
            $table->string('category'); // e.g., "Streaming", "Gym", "Software"
            $table->decimal('amount', 10, 2);
            $table->string('frequency'); // e.g., "monthly", "yearly"
            $table->date('start_date');
            $table->date('next_billing_date');
            $table->string('status')->default('active'); // e.g., "active", "paused", "canceled"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
