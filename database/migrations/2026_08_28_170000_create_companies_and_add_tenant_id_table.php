<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create Companies (Tenants) Table
        if (!Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('logo')->nullable();
                $table->string('domain')->nullable()->unique();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->string('subscription_plan')->default('starter'); // starter, pro, enterprise
                $table->string('status')->default('active'); // active, trial, suspended
                $table->integer('max_users')->default(10);
                $table->integer('max_projects')->default(5);
                $table->date('expires_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create default initial Tenant Company for existing system data
        $defaultCompanyId = DB::table('companies')->insertGetId([
            'name' => 'PT. Serangkai Roden Development',
            'slug' => 'serangkai-roden',
            'subscription_plan' => 'enterprise',
            'status' => 'active',
            'max_users' => 100,
            'max_projects' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Add company_id foreign key column to all tenant-scoped tables
        $tables = [
            'users', 'projects', 'unit_types', 'units', 'leads', 
            'bookings', 'expenses', 'general_ledger', 'bank_accounts', 
            'settings', 'broker_companies', 'contractor_contracts', 'rab_items'
        ];

        foreach ($tables as $tbl) {
            if (Schema::hasTable($tbl) && !Schema::hasColumn($tbl, 'company_id')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->foreignId('company_id')->nullable()->after('id')->index();
                });

                // Backfill existing data with defaultCompanyId
                DB::table($tbl)->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'users', 'projects', 'unit_types', 'units', 'leads', 
            'bookings', 'expenses', 'general_ledger', 'bank_accounts', 
            'settings', 'broker_companies', 'contractor_contracts', 'rab_items'
        ];

        foreach ($tables as $tbl) {
            if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'company_id')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->dropColumn('company_id');
                });
            }
        }

        Schema::dropIfExists('companies');
    }
};
