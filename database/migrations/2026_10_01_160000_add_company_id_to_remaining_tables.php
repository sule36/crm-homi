<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add company_id to ALL remaining operational tables for complete multi-tenant isolation.
     */
    public function up(): void
    {
        $tables = [
            'bank_accounts',
            'expenses',
            'general_ledger',
            'contractor_contracts',
            'contractor_termins',
            'rab_items',
            'rab_realizations',
            'unit_types',
            'settings',
            'audit_logs',
            'booking_documents',
            'chat_messages',
            'follow_up_reminders',
            'lead_activities',
            'payroll_deductions',
            'unit_price_histories',
            'unit_progresses',
            'unit_status_histories',
        ];

        // 1. Add company_id column to tables that lack it
        foreach ($tables as $tbl) {
            if (Schema::hasTable($tbl) && !Schema::hasColumn($tbl, 'company_id')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->foreignId('company_id')->nullable()->after('id')->index();
                });
            }
        }

        // 2. Determine default company
        $defaultCompanyId = DB::table('companies')->where('id', 1)->value('id');
        if (!$defaultCompanyId) {
            $defaultCompanyId = DB::table('companies')->first()?->id ?? 1;
        }

        // 3. Backfill via parent relationships where possible
        try {
            // Expenses -> via project_id
            if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'company_id')) {
                DB::table('expenses')
                    ->whereNull('company_id')
                    ->whereNotNull('project_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM projects WHERE projects.id = expenses.project_id LIMIT 1)")
                    ]);
                DB::table('expenses')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            // GeneralLedger -> via project_id
            if (Schema::hasTable('general_ledger') && Schema::hasColumn('general_ledger', 'company_id')) {
                DB::table('general_ledger')
                    ->whereNull('company_id')
                    ->whereNotNull('project_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM projects WHERE projects.id = general_ledger.project_id LIMIT 1)")
                    ]);
                DB::table('general_ledger')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            // ContractorContracts -> via project_id
            if (Schema::hasTable('contractor_contracts') && Schema::hasColumn('contractor_contracts', 'company_id')) {
                DB::table('contractor_contracts')
                    ->whereNull('company_id')
                    ->whereNotNull('project_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM projects WHERE projects.id = contractor_contracts.project_id LIMIT 1)")
                    ]);
                DB::table('contractor_contracts')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            // ContractorTermins -> via contractor_contract_id
            if (Schema::hasTable('contractor_termins') && Schema::hasColumn('contractor_termins', 'company_id')) {
                DB::table('contractor_termins')
                    ->whereNull('company_id')
                    ->whereNotNull('contractor_contract_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM contractor_contracts WHERE contractor_contracts.id = contractor_termins.contractor_contract_id LIMIT 1)")
                    ]);
                DB::table('contractor_termins')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            // RabItems -> via project_id
            if (Schema::hasTable('rab_items') && Schema::hasColumn('rab_items', 'company_id')) {
                DB::table('rab_items')
                    ->whereNull('company_id')
                    ->whereNotNull('project_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM projects WHERE projects.id = rab_items.project_id LIMIT 1)")
                    ]);
                DB::table('rab_items')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            // RabRealizations -> via rab_item_id
            if (Schema::hasTable('rab_realizations') && Schema::hasColumn('rab_realizations', 'company_id')) {
                DB::table('rab_realizations')
                    ->whereNull('company_id')
                    ->whereNotNull('rab_item_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM rab_items WHERE rab_items.id = rab_realizations.rab_item_id LIMIT 1)")
                    ]);
                DB::table('rab_realizations')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            // UnitTypes -> via project_id (if exists)
            if (Schema::hasTable('unit_types') && Schema::hasColumn('unit_types', 'company_id')) {
                if (Schema::hasColumn('unit_types', 'project_id')) {
                    DB::table('unit_types')
                        ->whereNull('company_id')
                        ->whereNotNull('project_id')
                        ->update([
                            'company_id' => DB::raw("(SELECT company_id FROM projects WHERE projects.id = unit_types.project_id LIMIT 1)")
                        ]);
                }
                DB::table('unit_types')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            // BookingDocuments -> via booking_id
            if (Schema::hasTable('booking_documents') && Schema::hasColumn('booking_documents', 'company_id')) {
                DB::table('booking_documents')
                    ->whereNull('company_id')
                    ->whereNotNull('booking_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM bookings WHERE bookings.id = booking_documents.booking_id LIMIT 1)")
                    ]);
                DB::table('booking_documents')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            // LeadActivities -> via lead_id
            if (Schema::hasTable('lead_activities') && Schema::hasColumn('lead_activities', 'company_id')) {
                DB::table('lead_activities')
                    ->whereNull('company_id')
                    ->whereNotNull('lead_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM leads WHERE leads.id = lead_activities.lead_id LIMIT 1)")
                    ]);
                DB::table('lead_activities')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            // FollowUpReminders -> via lead_id
            if (Schema::hasTable('follow_up_reminders') && Schema::hasColumn('follow_up_reminders', 'company_id')) {
                DB::table('follow_up_reminders')
                    ->whereNull('company_id')
                    ->whereNotNull('lead_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM leads WHERE leads.id = follow_up_reminders.lead_id LIMIT 1)")
                    ]);
                DB::table('follow_up_reminders')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            // ChatMessages -> via lead_id
            if (Schema::hasTable('chat_messages') && Schema::hasColumn('chat_messages', 'company_id')) {
                if (Schema::hasColumn('chat_messages', 'lead_id')) {
                    DB::table('chat_messages')
                        ->whereNull('company_id')
                        ->whereNotNull('lead_id')
                        ->update([
                            'company_id' => DB::raw("(SELECT company_id FROM leads WHERE leads.id = chat_messages.lead_id LIMIT 1)")
                        ]);
                }
                DB::table('chat_messages')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            // PayrollDeductions -> via payroll_id
            if (Schema::hasTable('payroll_deductions') && Schema::hasColumn('payroll_deductions', 'company_id')) {
                DB::table('payroll_deductions')
                    ->whereNull('company_id')
                    ->whereNotNull('payroll_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM payrolls WHERE payrolls.id = payroll_deductions.payroll_id LIMIT 1)")
                    ]);
                DB::table('payroll_deductions')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            // UnitPriceHistories -> via unit_id
            if (Schema::hasTable('unit_price_histories') && Schema::hasColumn('unit_price_histories', 'company_id')) {
                DB::table('unit_price_histories')
                    ->whereNull('company_id')
                    ->whereNotNull('unit_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM units WHERE units.id = unit_price_histories.unit_id LIMIT 1)")
                    ]);
                DB::table('unit_price_histories')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            // UnitProgresses -> via unit_id
            if (Schema::hasTable('unit_progresses') && Schema::hasColumn('unit_progresses', 'company_id')) {
                DB::table('unit_progresses')
                    ->whereNull('company_id')
                    ->whereNotNull('unit_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM units WHERE units.id = unit_progresses.unit_id LIMIT 1)")
                    ]);
                DB::table('unit_progresses')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            // UnitStatusHistories -> via unit_id
            if (Schema::hasTable('unit_status_histories') && Schema::hasColumn('unit_status_histories', 'company_id')) {
                DB::table('unit_status_histories')
                    ->whereNull('company_id')
                    ->whereNotNull('unit_id')
                    ->update([
                        'company_id' => DB::raw("(SELECT company_id FROM units WHERE units.id = unit_status_histories.unit_id LIMIT 1)")
                    ]);
                DB::table('unit_status_histories')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }

            // Remaining tables - just default backfill
            $remaining = ['bank_accounts', 'settings', 'audit_logs'];
            foreach ($remaining as $t) {
                if (Schema::hasTable($t) && Schema::hasColumn($t, 'company_id')) {
                    DB::table($t)->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
                }
            }

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Multi-tenant remaining tables backfill: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'bank_accounts',
            'expenses',
            'general_ledger',
            'contractor_contracts',
            'contractor_termins',
            'rab_items',
            'rab_realizations',
            'unit_types',
            'settings',
            'audit_logs',
            'booking_documents',
            'chat_messages',
            'follow_up_reminders',
            'lead_activities',
            'payroll_deductions',
            'unit_price_histories',
            'unit_progresses',
            'unit_status_histories',
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
