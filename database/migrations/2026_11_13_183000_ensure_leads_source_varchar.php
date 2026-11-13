<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('leads') || !Schema::hasColumn('leads', 'source')) {
            return;
        }

        // Convert leads.source from legacy ENUM to VARCHAR(50) so 'inhouse', 'independent', etc. are supported.
        try {
            DB::statement("ALTER TABLE leads MODIFY COLUMN source VARCHAR(50) NOT NULL DEFAULT 'other'");
        } catch (\Throwable $e) {
            try {
                Schema::table('leads', function (Blueprint $table) {
                    $table->string('source', 50)->default('other')->change();
                });
            } catch (\Throwable $e2) {
                // Ignore if driver does not support altering enum columns (e.g., local SQLite).
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep VARCHAR(50) on rollback to avoid truncating 'inhouse' or 'independent' records.
    }
};

