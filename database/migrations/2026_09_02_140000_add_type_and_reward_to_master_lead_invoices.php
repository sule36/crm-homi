<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_lead_invoices')) {
            Schema::table('master_lead_invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('master_lead_invoices', 'invoice_type')) {
                    $table->string('invoice_type')->default('commission'); // commission, closing_fee, reward
                }
                if (!Schema::hasColumn('master_lead_invoices', 'reward_name')) {
                    $table->string('reward_name')->nullable();
                }
                if (!Schema::hasColumn('master_lead_invoices', 'fee_per_unit')) {
                    $table->decimal('fee_per_unit', 15, 2)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('master_lead_invoices')) {
            Schema::table('master_lead_invoices', function (Blueprint $table) {
                if (Schema::hasColumn('master_lead_invoices', 'invoice_type')) {
                    $table->dropColumn(['invoice_type', 'reward_name', 'fee_per_unit']);
                }
            });
        }
    }
};
