<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add kpr_bank_name to bookings (was in model fillable but missing from DB)
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'kpr_bank_name')) {
                $table->string('kpr_bank_name')->nullable()->after('kpr_status');
            }
        });

        // 2. Add bank info columns to users (for commission disbursement)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('commission_rate');
            }
            if (!Schema::hasColumn('users', 'bank_account_number')) {
                $table->string('bank_account_number')->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('users', 'bank_account_name')) {
                $table->string('bank_account_name')->nullable()->after('bank_account_number');
            }
        });

        // 3. Add receipt_number to commissions (for payment receipt tracking)
        Schema::table('commissions', function (Blueprint $table) {
            if (!Schema::hasColumn('commissions', 'receipt_number')) {
                $table->string('receipt_number')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['kpr_bank_name']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account_number', 'bank_account_name']);
        });
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropColumn(['receipt_number']);
        });
    }
};
