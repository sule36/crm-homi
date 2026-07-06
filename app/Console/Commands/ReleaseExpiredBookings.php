<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\Unit;
use Illuminate\Support\Facades\Log;

class ReleaseExpiredBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:release-expired-bookings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release units from pending bookings that have expired (older than 14 days)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expirationDate = now()->subDays(14)->toDateString();

        $expiredBookings = Booking::where('status', 'pending')
            ->whereDate('booking_date', '<=', $expirationDate)
            ->get();

        if ($expiredBookings->isEmpty()) {
            $this->info('No expired bookings found.');
            return;
        }

        $count = 0;
        foreach ($expiredBookings as $booking) {
            // Cancel the booking
            $booking->update([
                'status' => 'cancelled',
                'cancelled_reason' => 'Otomatis dibatalkan: Melewati batas waktu penahanan 14 hari.',
            ]);

            // Release the unit
            if ($booking->unit_id) {
                Unit::where('id', $booking->unit_id)->update(['status' => 'available']);
            }

            // Log activity
            \App\Models\AuditLog::record('updated', $booking, null, ['status' => 'cancelled', 'reason' => '14-day expiration auto-release']);

            // Alert the agent who booked it
            \App\Models\FollowUpReminder::create([
                'lead_id' => $booking->lead_id,
                'user_id' => $booking->booked_by,
                'remind_at' => now(),
                'message' => "SYSTEM ALERT: Unit untuk SPK {$booking->spk_number} telah otomatis dirilis ke publik karena belum ada pembayaran selama 14 hari.",
            ]);

            $count++;
        }

        $this->info("Successfully released {$count} expired bookings.");
        Log::info("Released {$count} expired bookings (14-day limit).");
    }
}
