<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('commission_amount', 15, 0)->default(0)->after('notes');
            $table->enum('commission_status', ['unpaid', 'paid'])->default('unpaid')->after('commission_amount');
            $table->timestamp('commission_paid_at')->nullable()->after('commission_status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['commission_amount', 'commission_status', 'commission_paid_at']);
        });
    }
};
