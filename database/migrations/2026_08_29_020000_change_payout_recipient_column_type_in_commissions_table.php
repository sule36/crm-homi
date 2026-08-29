<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement("ALTER TABLE commissions MODIFY payout_recipient VARCHAR(50) NULL DEFAULT 'agent'");
        } catch (\Throwable $e) {
            Schema::table('commissions', function (Blueprint $table) {
                $table->string('payout_recipient', 50)->nullable()->default('agent')->change();
            });
        }
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE commissions MODIFY payout_recipient VARCHAR(50) NULL DEFAULT 'agent'");
        } catch (\Throwable $e) {}
    }
};
