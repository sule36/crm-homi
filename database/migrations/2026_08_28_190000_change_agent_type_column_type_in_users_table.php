<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement("ALTER TABLE users MODIFY agent_type VARCHAR(50) NULL DEFAULT 'inhouse'");
        } catch (\Throwable $e) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('agent_type', 50)->nullable()->default('inhouse')->change();
            });
        }
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE users MODIFY agent_type VARCHAR(50) NULL DEFAULT 'inhouse'");
        } catch (\Throwable $e) {}
    }
};
