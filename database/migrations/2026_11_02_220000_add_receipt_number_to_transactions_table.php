<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transactions') && !Schema::hasColumn('transactions', 'receipt_number')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('receipt_number')->nullable()->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'receipt_number')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('receipt_number');
            });
        }
    }
};
