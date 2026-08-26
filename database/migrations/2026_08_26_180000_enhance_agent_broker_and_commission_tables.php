<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'agent_type')) {
                $table->enum('agent_type', ['inhouse', 'agency_agent', 'independent'])->default('inhouse')->after('broker_company_id');
            }
            if (!Schema::hasColumn('users', 'custom_bonus')) {
                $table->decimal('custom_bonus', 15, 2)->default(0)->after('commission_rate');
            }
        });

        Schema::table('broker_companies', function (Blueprint $table) {
            if (!Schema::hasColumn('broker_companies', 'code')) {
                $table->string('code')->nullable()->after('name');
            }
            if (!Schema::hasColumn('broker_companies', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('address');
            }
            if (!Schema::hasColumn('broker_companies', 'bank_account_number')) {
                $table->string('bank_account_number')->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('broker_companies', 'bank_account_name')) {
                $table->string('bank_account_name')->nullable()->after('bank_account_number');
            }
            if (!Schema::hasColumn('broker_companies', 'notes')) {
                $table->text('notes')->nullable()->after('bank_account_name');
            }
        });

        Schema::table('commissions', function (Blueprint $table) {
            if (!Schema::hasColumn('commissions', 'broker_company_id')) {
                $table->foreignId('broker_company_id')->nullable()->constrained('broker_companies')->nullOnDelete()->after('user_id');
            }
            if (!Schema::hasColumn('commissions', 'base_commission')) {
                $table->decimal('base_commission', 15, 2)->default(0)->after('amount');
            }
            if (!Schema::hasColumn('commissions', 'promo_bonus')) {
                $table->decimal('promo_bonus', 15, 2)->default(0)->after('base_commission');
            }
            if (!Schema::hasColumn('commissions', 'rate_used')) {
                $table->decimal('rate_used', 5, 2)->default(0)->after('promo_bonus');
            }
            if (!Schema::hasColumn('commissions', 'payout_recipient')) {
                $table->enum('payout_recipient', ['agent', 'agency'])->default('agent')->after('rate_used');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['agent_type', 'custom_bonus']);
        });

        Schema::table('broker_companies', function (Blueprint $table) {
            $table->dropColumn(['code', 'bank_name', 'bank_account_number', 'bank_account_name', 'notes']);
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->dropForeign(['broker_company_id']);
            $table->dropColumn(['broker_company_id', 'base_commission', 'promo_bonus', 'rate_used', 'payout_recipient']);
        });
    }
};
