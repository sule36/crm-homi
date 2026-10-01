<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'leads', 'bookings', 'units', 'projects', 'unit_types', 
            'expenses', 'general_ledger', 'bank_accounts', 'settings', 
            'broker_companies', 'contractor_contracts', 'rab_items',
            'reservations', 'negotiations', 'users'
        ];

        // Ensure default developer company exists
        $companyId = DB::table('companies')->where('id', 1)->value('id');
        if (!$companyId) {
            $companyId = DB::table('companies')->insertGetId([
                'id' => 1,
                'name' => 'PT. Serangkai Roden Development',
                'slug' => 'serangkai-roden',
                'subscription_plan' => 'enterprise',
                'status' => 'active',
                'max_users' => 100,
                'max_projects' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($tables as $tbl) {
            if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'company_id')) {
                if ($tbl === 'users') {
                    // Do not update SuperAdmin homi
                    DB::table('users')
                        ->whereNull('company_id')
                        ->where('email', '!=', 'admin@homi.id')
                        ->update(['company_id' => $companyId]);
                } else {
                    DB::table($tbl)
                        ->whereNull('company_id')
                        ->update(['company_id' => $companyId]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op for backfill
    }
};
