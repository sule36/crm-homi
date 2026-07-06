<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('floor_plan_file')->nullable()->after('status');
        });
        
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('proof_file')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('units', fn($table) => $table->dropColumn(['floor_plan_file']));
        Schema::table('transactions', fn($table) => $table->dropColumn(['proof_file']));
    }
};
