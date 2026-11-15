<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        $now = now();

        // Force-disable existing rows for instagram_lead_capture_enabled
        DB::table('settings')
            ->where('key', 'instagram_lead_capture_enabled')
            ->update([
                'value' => '0',
                'updated_at' => $now,
            ]);

        // Ensure every company (or default company_id = 1) has an explicit '0' record
        $companyIds = Schema::hasTable('companies')
            ? DB::table('companies')->pluck('id')->all()
            : [1];

        if (empty($companyIds)) {
            $companyIds = [1];
        }

        foreach ($companyIds as $companyId) {
            $exists = DB::table('settings')
                ->where('key', 'instagram_lead_capture_enabled')
                ->where('company_id', $companyId)
                ->exists();

            if (!$exists) {
                DB::table('settings')->insert([
                    'company_id' => $companyId,
                    'key' => 'instagram_lead_capture_enabled',
                    'value' => '0',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: leave setting as configured by the user.
    }
};

