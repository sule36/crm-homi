<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations to isolate ALL tables by company_id for strict SaaS multi-tenancy.
     */
    public function up(): void
    {
        $tables = [
            'commissions',
            'master_lead_invoices',
            'transactions',
            'payrolls',
            'employee_salaries',
            'partner_banks',
            'expense_categories',
            'reservations',
            'negotiations',
            'client_balance_sheets',
            'campaigns',
            'sales_targets',
            'payment_schedules',
        ];

        // 1. Add company_id column to tables that lack it
        foreach ($tables as $tbl) {
            if (Schema::hasTable($tbl) && !Schema::hasColumn($tbl, 'company_id')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->foreignId('company_id')->nullable()->after('id')->index();
                });
            }
        }

        // 2. Ensure default company (PT. Serangkai Roden Development) exists
        $defaultCompanyId = DB::table('companies')->where('id', 1)->value('id');
        if (!$defaultCompanyId) {
            $defaultCompanyId = DB::table('companies')->first()?->id ?? 1;
        }

        // 3. Backfill smart parent-linked company_id or default to company 1
        try {
            if (Schema::hasTable('commissions') && Schema::hasColumn('commissions', 'company_id')) {
                DB::table('commissions')
                    ->whereNull('company_id')
                    ->whereNotNull('booking_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM bookings WHERE bookings.id = commissions.booking_id LIMIT 1)")
                    ]);
                DB::table('commissions')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'company_id')) {
                DB::table('transactions')
                    ->whereNull('company_id')
                    ->whereNotNull('booking_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM bookings WHERE bookings.id = transactions.booking_id LIMIT 1)")
                    ]);
                DB::table('transactions')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            if (Schema::hasTable('payment_schedules') && Schema::hasColumn('payment_schedules', 'company_id')) {
                DB::table('payment_schedules')
                    ->whereNull('company_id')
                    ->whereNotNull('booking_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM bookings WHERE bookings.id = payment_schedules.booking_id LIMIT 1)")
                    ]);
                DB::table('payment_schedules')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            if (Schema::hasTable('payrolls') && Schema::hasColumn('payrolls', 'company_id')) {
                DB::table('payrolls')
                    ->whereNull('company_id')
                    ->whereNotNull('user_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM users WHERE users.id = payrolls.user_id LIMIT 1)")
                    ]);
                DB::table('payrolls')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            if (Schema::hasTable('employee_salaries') && Schema::hasColumn('employee_salaries', 'company_id')) {
                DB::table('employee_salaries')
                    ->whereNull('company_id')
                    ->whereNotNull('user_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM users WHERE users.id = employee_salaries.user_id LIMIT 1)")
                    ]);
                DB::table('employee_salaries')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            if (Schema::hasTable('reservations') && Schema::hasColumn('reservations', 'company_id')) {
                DB::table('reservations')
                    ->whereNull('company_id')
                    ->whereNotNull('project_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM projects WHERE projects.id = reservations.project_id LIMIT 1)")
                    ]);
                DB::table('reservations')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            if (Schema::hasTable('negotiations') && Schema::hasColumn('negotiations', 'company_id')) {
                DB::table('negotiations')
                    ->whereNull('company_id')
                    ->whereNotNull('project_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM projects WHERE projects.id = negotiations.project_id LIMIT 1)")
                    ]);
                DB::table('negotiations')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            // Other backfills
            $allLegacyTables = [
                'broker_companies', 'rab_items', 'expenses', 'general_ledger',
                'bank_accounts', 'contractor_contracts', 'unit_types',
                'master_lead_invoices', 'partner_banks', 'expense_categories',
                'client_balance_sheets', 'campaigns', 'sales_targets'
            ];

            foreach ($allLegacyTables as $t) {
                if (Schema::hasTable($t) && Schema::hasColumn($t, 'company_id')) {
                    DB::table($t)->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
                }
            }

            // Ensure legacy users (except super admin) belong to default company
            if (Schema::hasTable('users') && Schema::hasColumn('users', 'company_id')) {
                DB::table('users')
                    ->whereNull('company_id')
                    ->where('email', '!=', 'admin@homi.id')
                    ->update(['company_id' => $defaultCompanyId]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Multi-tenant migration backfill notice: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'commissions',
            'master_lead_invoices',
            'transactions',
            'payrolls',
            'employee_salaries',
            'partner_banks',
            'expense_categories',
            'reservations',
            'negotiations',
            'client_balance_sheets',
            'campaigns',
            'sales_targets',
            'payment_schedules',
        ];

        foreach ($tables as $tbl) {
            if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'company_id')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->dropColumn('company_id');
                });
            }
        }
    }
};
