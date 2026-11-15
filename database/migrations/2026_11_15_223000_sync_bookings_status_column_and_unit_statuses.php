<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Project;
use App\Models\Unit;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $dbName = DB::connection()->getDatabaseName();
            foreach (['bookings' => 'pending', 'units' => 'available'] as $table => $default) {
                if (!Schema::hasTable($table)) {
                    continue;
                }
                $col = DB::selectOne(
                    "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = 'status'",
                    [$dbName, $table]
                );
                if ($col && strtolower((string) $col->DATA_TYPE) === 'enum') {
                    DB::statement("ALTER TABLE {$table} MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT '{$default}'");
                }
            }
        }

        if (Schema::hasTable('units') && Schema::hasTable('bookings')) {
            Unit::withoutGlobalScopes()->whereNull('deleted_at')->chunkById(200, function ($units) {
                foreach ($units as $unit) {
                    // Preserve explicit manual 'sold' / 'cancelled' states when there are no bookings overriding them
                    $currentStatus = strtolower(trim((string) $unit->status));
                    $hasBookings = DB::table('bookings')
                        ->where('unit_id', $unit->id)
                        ->whereNull('deleted_at')
                        ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('cancelled', 'rejected', 'expired')")
                        ->exists();
                    $hasActiveReservation = Schema::hasTable('reservations') && DB::table('reservations')
                        ->where('unit_id', $unit->id)
                        ->whereNull('deleted_at')
                        ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = 'active'")
                        ->exists();

                    if ($hasBookings || $hasActiveReservation || in_array($currentStatus, ['hold', 'reserved'], true)) {
                        $unit->syncStatusFromRecords();
                    }
                }
            });

            if (Schema::hasTable('projects')) {
                Project::withoutGlobalScopes()->each(function ($project) {
                    $project->recalculateUnits();
                });
            }
        }
    }

    public function down(): void
    {
        // Non-destructive status sync migration
    }
};

