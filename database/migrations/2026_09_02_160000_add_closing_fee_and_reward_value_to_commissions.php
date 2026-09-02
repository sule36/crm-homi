<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            if (!Schema::hasColumn('commissions', 'closing_fee')) {
                $table->decimal('closing_fee', 15, 2)->default(0)->after('amount');
            }
            if (!Schema::hasColumn('commissions', 'reward_value')) {
                $table->decimal('reward_value', 15, 2)->default(0)->after('closing_fee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropColumn(['closing_fee', 'reward_value']);
        });
    }
};
