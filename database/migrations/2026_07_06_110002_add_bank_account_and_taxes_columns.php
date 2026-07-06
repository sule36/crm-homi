<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add bank_account_id to transactions
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
        });

        // 2. Add bank_account_id, ppn_amount, and pph_amount to expenses
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->decimal('ppn_amount', 15, 0)->default(0);
            $table->decimal('pph_amount', 15, 0)->default(0);
        });

        // 3. Add bank_account_id to payrolls
        Schema::table('payrolls', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
        });

        // 4. Add bank_account_id to commissions
        Schema::table('commissions', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
        });

        // 5. Add bank_account_id to general_ledger
        Schema::table('general_ledger', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('general_ledger', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropColumn(['ppn_amount', 'pph_amount']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
        });
    }
};
