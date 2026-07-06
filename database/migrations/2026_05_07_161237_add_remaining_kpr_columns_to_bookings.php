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
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'kpr_plafon_amount')) {
                $table->decimal('kpr_plafon_amount', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('bookings', 'kpr_sp3k_date')) {
                $table->date('kpr_sp3k_date')->nullable();
            }
            if (!Schema::hasColumn('bookings', 'kpr_akad_date')) {
                $table->date('kpr_akad_date')->nullable();
            }
            if (!Schema::hasColumn('bookings', 'kpr_notes')) {
                $table->text('kpr_notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['kpr_plafon_amount', 'kpr_sp3k_date', 'kpr_akad_date', 'kpr_notes']);
        });
    }
};
