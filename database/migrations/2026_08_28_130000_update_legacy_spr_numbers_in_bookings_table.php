<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Booking;

return new class extends Migration
{
    public function up(): void
    {
        try {
            \App\Models\Setting::set('spr_number_format', '{seq}/SPR-{code}/{month_roman}/{year}');

            $bookings = Booking::withTrashed()->with(['unit.project'])->get();
            foreach ($bookings as $b) {
                $b->spk_number = Booking::formatSprNumberForBooking($b);
                $b->saveQuietly();
            }
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
    }
};
