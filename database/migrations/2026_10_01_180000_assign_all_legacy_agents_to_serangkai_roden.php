<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations: Explicitly assign legacy agent network, brokers, and operational data
     * to PT. Serangkai Roden Development to guarantee 100% strict data privacy across developers.
     */
    public function up(): void
    {
        try {
            if (!Schema::hasTable('companies')) {
                return;
            }

            // 1. Find PT. Serangkai Roden Development
            $serangkaiCompany = DB::table('companies')
                ->where('name', 'like', '%Serangkai Roden%')
                ->first();

            if (!$serangkaiCompany) {
                $serangkaiCompany = DB::table('companies')->orderBy('id', 'asc')->first();
            }

            if (!$serangkaiCompany) {
                return;
            }

            $serangkaiId = $serangkaiCompany->id;

            // 2. Identify other developer companies (e.g. PT Homi Citra Nusantara)
            $otherCompanyIds = DB::table('companies')
                ->where('id', '!=', $serangkaiId)
                ->pluck('id')
                ->toArray();

            // 3. Specifically assign KANAHOMI and Sub-Agents to PT. Serangkai Roden Development
            if (Schema::hasTable('users')) {
                $kanahomi = DB::table('users')
                    ->where(function ($q) {
                        $q->where('name', 'like', '%KANAHOMI%')
                          ->orWhere('email', 'sulaiman@homi.id');
                    })
                    ->first();

                if ($kanahomi) {
                    DB::table('users')->where('id', $kanahomi->id)->update(['company_id' => $serangkaiId]);
                    DB::table('users')->where('master_lead_id', $kanahomi->id)->update(['company_id' => $serangkaiId]);
                }

                // Any legacy users that do not belong to another registered company and are not super admin
                DB::table('users')
                    ->where('email', '!=', 'admin@homi.id')
                    ->where(function ($q) use ($otherCompanyIds) {
                        $q->whereNull('company_id');
                        if (!empty($otherCompanyIds)) {
                            $q->orWhereNotIn('company_id', $otherCompanyIds);
                        }
                    })
                    ->update(['company_id' => $serangkaiId]);
            }

            // 4. Assign legacy broker companies to Serangkai Roden
            if (Schema::hasTable('broker_companies') && Schema::hasColumn('broker_companies', 'company_id')) {
                DB::table('broker_companies')
                    ->where(function ($q) use ($otherCompanyIds) {
                        $q->whereNull('company_id');
                        if (!empty($otherCompanyIds)) {
                            $q->orWhereNotIn('company_id', $otherCompanyIds);
                        }
                    })
                    ->update(['company_id' => $serangkaiId]);
            }

            // 5. Assign legacy commissions
            if (Schema::hasTable('commissions') && Schema::hasColumn('commissions', 'company_id')) {
                if (Schema::hasColumn('commissions', 'booking_id') && Schema::hasTable('bookings')) {
                    DB::table('commissions')
                        ->whereNull('company_id')
                        ->whereNotNull('booking_id')
                        ->update([
                            'company_id' => DB::raw("(SELECT company_id FROM bookings WHERE bookings.id = commissions.booking_id LIMIT 1)")
                        ]);
                }

                DB::table('commissions')
                    ->where(function ($q) use ($otherCompanyIds) {
                        $q->whereNull('company_id');
                        if (!empty($otherCompanyIds)) {
                            $q->orWhereNotIn('company_id', $otherCompanyIds);
                        }
                    })
                    ->update(['company_id' => $serangkaiId]);
            }

            // 6. Assign legacy master lead invoices
            if (Schema::hasTable('master_lead_invoices') && Schema::hasColumn('master_lead_invoices', 'company_id')) {
                if (Schema::hasColumn('master_lead_invoices', 'master_lead_id') && Schema::hasTable('users')) {
                    DB::table('master_lead_invoices')
                        ->whereNull('company_id')
                        ->whereNotNull('master_lead_id')
                        ->update([
                            'company_id' => DB::raw("(SELECT company_id FROM users WHERE users.id = master_lead_invoices.master_lead_id LIMIT 1)")
                        ]);
                }

                DB::table('master_lead_invoices')
                    ->where(function ($q) use ($otherCompanyIds) {
                        $q->whereNull('company_id');
                        if (!empty($otherCompanyIds)) {
                            $q->orWhereNotIn('company_id', $otherCompanyIds);
                        }
                    })
                    ->update(['company_id' => $serangkaiId]);
            }

            // 7. Ensure all other operational tables have non-null company_id
            $allOperationalTables = [
                'projects', 'units', 'unit_types', 'leads', 'bookings',
                'expenses', 'general_ledger', 'bank_accounts', 'settings',
                'contractor_contracts', 'rab_items', 'partner_banks',
                'expense_categories', 'reservations', 'negotiations',
                'client_balance_sheets', 'campaigns', 'sales_targets',
                'payrolls', 'employee_salaries', 'payment_schedules', 'transactions'
            ];

            foreach ($allOperationalTables as $tbl) {
                if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'company_id')) {
                    DB::table($tbl)
                        ->whereNull('company_id')
                        ->update(['company_id' => $serangkaiId]);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Migration assign_all_legacy_agents_to_serangkai_roden warning: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op to preserve data integrity
    }
};
