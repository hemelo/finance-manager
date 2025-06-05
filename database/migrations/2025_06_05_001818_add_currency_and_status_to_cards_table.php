<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('USD')->after('brand');
            $table->string('status', 20)->default('active')->after('is_cashback_per_transaction'); // active, inactive
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('currency_code');
            $table->dropColumn('status');
        });
    }
};
