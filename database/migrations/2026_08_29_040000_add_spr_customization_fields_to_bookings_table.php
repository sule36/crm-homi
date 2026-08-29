<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'secondary_npwp')) {
                $table->string('secondary_npwp')->nullable()->after('secondary_nik');
            }
            if (!Schema::hasColumn('bookings', 'spr_date')) {
                $table->date('spr_date')->nullable()->after('booking_date');
            }
            if (!Schema::hasColumn('bookings', 'spr_schedule_dates')) {
                $table->json('spr_schedule_dates')->nullable()->after('spr_special_offer');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['secondary_npwp', 'spr_date', 'spr_schedule_dates']);
        });
    }
};
