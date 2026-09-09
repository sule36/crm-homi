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
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'receipt_title')) {
                $table->string('receipt_title')->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('reservations', 'city')) {
                $table->string('city')->nullable()->after('receipt_title');
            }
            if (!Schema::hasColumn('reservations', 'terms_text')) {
                $table->text('terms_text')->nullable()->after('city');
            }
            if (!Schema::hasColumn('reservations', 'policy_title')) {
                $table->string('policy_title')->nullable()->after('terms_text');
            }
            if (!Schema::hasColumn('reservations', 'policy_text')) {
                $table->text('policy_text')->nullable()->after('policy_title');
            }
            if (!Schema::hasColumn('reservations', 'custom_overrides')) {
                $table->json('custom_overrides')->nullable()->after('policy_text');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_title',
                'city',
                'terms_text',
                'policy_title',
                'policy_text',
                'custom_overrides',
            ]);
        });
    }
};
