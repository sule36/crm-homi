<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicTrackingController extends Controller
{
    public function show($token)
    {
        $booking = Booking::with(['lead', 'unit.project', 'unit.unitType', 'paymentSchedules', 'transactions'])
            ->where('tracking_token', $token)
            ->firstOrFail();

        return Inertia::render('Public/BookingTracking', [
            'booking' => $booking,
        ]);
    }
}
