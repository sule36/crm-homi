<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'bank_account_id')) {
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete()->after('bank_name');
            }
            if (!Schema::hasColumn('bookings', 'spr_terms_conditions')) {
                $table->json('spr_terms_conditions')->nullable()->after('special_package_items');
            }
            if (!Schema::hasColumn('bookings', 'spr_bank_info')) {
                $table->json('spr_bank_info')->nullable()->after('spr_terms_conditions');
            }
            if (!Schema::hasColumn('bookings', 'spr_special_offer')) {
                $table->json('spr_special_offer')->nullable()->after('spr_bank_info');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn([
                'bank_account_id',
                'spr_terms_conditions',
                'spr_bank_info',
                'spr_special_offer',
            ]);
        });
    }
};
