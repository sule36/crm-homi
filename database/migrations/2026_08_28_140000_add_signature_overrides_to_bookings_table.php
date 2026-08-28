<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'sig1_title')) {
                $table->string('sig1_title')->nullable()->after('secondary_email');
                $table->string('sig1_name')->nullable()->after('sig1_title');
                $table->string('sig2_title')->nullable()->after('sig1_name');
                $table->string('sig2_name')->nullable()->after('sig2_title');
                $table->string('sig3_title')->nullable()->after('sig2_name');
                $table->string('sig3_name')->nullable()->after('sig3_title');
                $table->string('sig4_title')->nullable()->after('sig3_name');
                $table->string('sig4_name')->nullable()->after('sig4_title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'sig1_title', 'sig1_name',
                'sig2_title', 'sig2_name',
                'sig3_title', 'sig3_name',
                'sig4_title', 'sig4_name',
            ]);
        });
    }
};
