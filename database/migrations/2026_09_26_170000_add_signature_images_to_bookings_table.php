<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'sig1_image')) {
                $table->text('sig1_image')->nullable()->after('sig1_name');
            }
            if (!Schema::hasColumn('bookings', 'sig2_image')) {
                $table->text('sig2_image')->nullable()->after('sig2_name');
            }
            if (!Schema::hasColumn('bookings', 'sig3_image')) {
                $table->longText('sig3_image')->nullable()->after('sig3_name');
            }
            if (!Schema::hasColumn('bookings', 'sig4_image')) {
                $table->longText('sig4_image')->nullable()->after('sig4_name');
            }
            if (!Schema::hasColumn('bookings', 'agent_signed_at')) {
                $table->timestamp('agent_signed_at')->nullable()->after('sig3_image');
            }
            if (!Schema::hasColumn('bookings', 'customer_signed_at')) {
                $table->timestamp('customer_signed_at')->nullable()->after('sig4_image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $cols = ['sig1_image', 'sig2_image', 'sig3_image', 'sig4_image', 'agent_signed_at', 'customer_signed_at'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('bookings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
