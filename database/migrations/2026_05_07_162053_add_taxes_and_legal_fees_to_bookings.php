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
            $table->decimal('base_price', 15, 2)->default(0)->after('unit_price');
            $table->decimal('ppn_amount', 15, 2)->default(0)->after('base_price');
            $table->decimal('bphtb_amount', 15, 2)->default(0)->after('ppn_amount');
            $table->decimal('ajb_bbn_amount', 15, 2)->default(0)->after('bphtb_amount');
            $table->decimal('other_legal_fees', 15, 2)->default(0)->after('ajb_bbn_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['base_price', 'ppn_amount', 'bphtb_amount', 'ajb_bbn_amount', 'other_legal_fees']);
        });
    }
};
