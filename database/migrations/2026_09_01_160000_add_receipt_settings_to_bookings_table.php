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
        if (!Schema::hasColumn('bookings', 'receipt_settings')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->json('receipt_settings')->nullable()->after('spr_schedule_dates');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'receipt_settings')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('receipt_settings');
            });
        }
    }
};
