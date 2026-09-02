<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            if (!Schema::hasColumn('commissions', 'claim_type')) {
                $table->string('claim_type')->default('commission')->after('payout_recipient'); // commission, closing_fee, reward
            }
            if (!Schema::hasColumn('commissions', 'reward_name')) {
                $table->string('reward_name')->nullable()->after('claim_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropColumn(['claim_type', 'reward_name']);
        });
    }
};
