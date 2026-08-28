<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'master_lead_id')) {
                $table->foreignId('master_lead_id')->nullable()->after('broker_company_id')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('broker_companies', function (Blueprint $table) {
            if (!Schema::hasColumn('broker_companies', 'master_lead_id')) {
                $table->foreignId('master_lead_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'master_lead_id')) {
                $table->dropForeign(['master_lead_id']);
                $table->dropColumn('master_lead_id');
            }
        });

        Schema::table('broker_companies', function (Blueprint $table) {
            if (Schema::hasColumn('broker_companies', 'master_lead_id')) {
                $table->dropForeign(['master_lead_id']);
                $table->dropColumn('master_lead_id');
            }
        });
    }
};
