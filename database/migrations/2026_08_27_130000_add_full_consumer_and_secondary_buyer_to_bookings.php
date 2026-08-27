<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('buyer_nik')->nullable()->after('booked_by');
            $table->string('buyer_npwp')->nullable()->after('buyer_nik');
            $table->text('buyer_address')->nullable()->after('buyer_npwp');
            $table->string('buyer_job')->nullable()->after('buyer_address');

            $table->string('secondary_name')->nullable()->after('buyer_job');
            $table->string('secondary_nik')->nullable()->after('secondary_name');
            $table->string('secondary_phone')->nullable()->after('secondary_nik');
            $table->string('secondary_relationship')->nullable()->after('secondary_phone');

            $table->json('special_bonus_items')->nullable()->after('secondary_relationship');
            $table->json('special_package_items')->nullable()->after('special_bonus_items');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('npwp')->nullable()->after('identity_number');
            $table->text('address')->nullable()->after('npwp');
            $table->string('job')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'buyer_nik', 'buyer_npwp', 'buyer_address', 'buyer_job',
                'secondary_name', 'secondary_nik', 'secondary_phone', 'secondary_relationship',
                'special_bonus_items', 'special_package_items'
            ]);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['npwp', 'address', 'job']);
        });
    }
};
