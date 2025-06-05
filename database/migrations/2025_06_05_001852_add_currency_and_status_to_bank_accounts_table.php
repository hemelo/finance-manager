<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('USD')->after('account_number');
            $table->string('status', 20)->default('active')->after('balance'); // active, inactive
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('currency_code');
            $table->dropColumn('status');
        });
    }
};
