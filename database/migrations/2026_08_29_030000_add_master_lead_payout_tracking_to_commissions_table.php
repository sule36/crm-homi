<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            if (!Schema::hasColumn('commissions', 'ml_payout_status')) {
                $table->string('ml_payout_status')->default('unpaid')->after('status');
            }
            if (!Schema::hasColumn('commissions', 'ml_paid_at')) {
                $table->timestamp('ml_paid_at')->nullable()->after('ml_payout_status');
            }
            if (!Schema::hasColumn('commissions', 'ml_receipt_number')) {
                $table->string('ml_receipt_number')->nullable()->after('ml_paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropColumn(['ml_payout_status', 'ml_paid_at', 'ml_receipt_number']);
        });
    }
};
