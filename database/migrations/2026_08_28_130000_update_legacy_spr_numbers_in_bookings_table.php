<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Booking;

return new class extends Migration
{
    public function up(): void
    {
        try {
            $bookings = Booking::withTrashed()->with(['unit.project'])->get();
            foreach ($bookings as $b) {
                if (empty($b->spk_number) || str_contains($b->spk_number, 'HOMI') || str_starts_with($b->spk_number, 'SPR-2026-')) {
                    $b->spk_number = Booking::formatSprNumberForBooking($b);
                    $b->saveQuietly();
                }
            }
        } catch (\Throwable $e) {
            // Log error silently if table not populated yet
        }
    }

    public function down(): void
    {
    }
};
