<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire';
    protected $description = 'Auto-expire reservations past their expiry date and release held units';

    public function handle(): int
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('reservations')) {
            $this->info('Reservations table does not exist. Skipping.');
            return self::SUCCESS;
        }

        $expired = Reservation::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with('unit')
            ->get();

        $count = 0;
        foreach ($expired as $reservation) {
            $reservation->update(['status' => 'cancelled']);

            // Release unit back to available
            if ($reservation->unit && in_array($reservation->unit->status, ['reserved', 'hold'])) {
                $reservation->unit->update([
                    'status' => 'available',
                    'held_by' => null,
                    'held_until' => null,
                ]);
            }

            // Restore lead status to negotiation so sales can follow up
            if ($reservation->lead && $reservation->lead->status === 'reservation') {
                $reservation->lead->update(['status' => 'negotiation']);
                $reservation->lead->recalculateScore();
            }

            try {
                AuditLog::record('reservation_auto_expired', $reservation, null, [
                    'expires_at' => $reservation->expires_at?->toDateTimeString(),
                    'unit_id' => $reservation->unit_id,
                ]);
            } catch (\Throwable $e) {
                Log::warning("AuditLog for auto-expire skipped: " . $e->getMessage());
            }

            $count++;
        }

        $this->info("Expired {$count} reservations and released their units.");
        return self::SUCCESS;
    }
}
