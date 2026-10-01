<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Scope unique constraints to (company_id, key/code) so tenants can have independent settings and categories.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            try {
                DB::statement('DROP INDEX IF EXISTS settings_key_unique');
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS settings_company_id_key_unique ON settings (company_id, key)');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('SQLite settings index update: ' . $e->getMessage());
            }

            try {
                DB::statement('DROP INDEX IF EXISTS expense_categories_code_unique');
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS expense_categories_company_id_code_unique ON expense_categories (company_id, code)');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('SQLite expense_categories index update: ' . $e->getMessage());
            }
        } else {
            // MySQL / PostgreSQL
            try {
                Schema::table('settings', function (Blueprint $table) {
                    $table->dropUnique('settings_key_unique');
                    $table->unique(['company_id', 'key']);
                });
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Settings unique index update: ' . $e->getMessage());
            }

            try {
                Schema::table('expense_categories', function (Blueprint $table) {
                    $table->dropUnique('expense_categories_code_unique');
                    $table->unique(['company_id', 'code']);
                });
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('ExpenseCategories unique index update: ' . $e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            try {
                DB::statement('DROP INDEX IF EXISTS settings_company_id_key_unique');
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS settings_key_unique ON settings (key)');
            } catch (\Throwable $e) {}
        } else {
            try {
                Schema::table('settings', function (Blueprint $table) {
                    $table->dropUnique(['company_id', 'key']);
                    $table->unique('key');
                });
            } catch (\Throwable $e) {}
        }
    }
};
