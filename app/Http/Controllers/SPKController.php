<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SPKController extends Controller
{
    public function download(Booking $booking)
    {
        $booking->load(['unit.project', 'unit.unitType', 'lead', 'bookedBy', 'paymentSchedules']);
        $settings = \App\Models\Setting::all()->pluck('value', 'key');

        $pdf = Pdf::loadView('pdf.spk', compact('booking', 'settings'));
        
        return $pdf->download("SPK-{$booking->spk_number}.pdf");
    }

    public function stream(Booking $booking)
    {
        $booking->load(['unit.project', 'unit.unitType', 'lead', 'bookedBy', 'paymentSchedules']);
        $settings = \App\Models\Setting::all()->pluck('value', 'key');

        $pdf = Pdf::loadView('pdf.spk', compact('booking', 'settings'));
        
        return $pdf->stream();
    }
}
