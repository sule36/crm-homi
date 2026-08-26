<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('partner_banks', function (Blueprint $table) {
            if (!Schema::hasColumn('partner_banks', 'fixed_programs')) {
                $table->text('fixed_programs')->nullable()->after('fixed_duration');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_banks', function (Blueprint $table) {
            if (Schema::hasColumn('partner_banks', 'fixed_programs')) {
                $table->dropColumn('fixed_programs');
            }
        });
    }
};
