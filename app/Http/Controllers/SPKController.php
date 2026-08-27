<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SPKController extends Controller
{
    private function getSettings()
    {
        $settingsRaw = Setting::all();
        $settings = [];
        foreach ($settingsRaw as $s) {
            $settings[$s->key] = Setting::get($s->key);
        }
        return $settings;
    }

    public function download(Booking $booking)
    {
        $booking->load(['unit.project', 'unit.unitType', 'lead', 'bookedBy', 'paymentSchedules']);
        $settings = $this->getSettings();

        $pdf = Pdf::loadView('pdf.spr', compact('booking', 'settings'));
        
        return $pdf->download("SPR-{$booking->spk_number}.pdf");
    }

    public function stream(Booking $booking)
    {
        $booking->load(['unit.project', 'unit.unitType', 'lead', 'bookedBy', 'paymentSchedules']);
        $settings = $this->getSettings();

        if (request()->has('html')) {
            return view('pdf.spr', compact('booking', 'settings'));
        }

        $pdf = Pdf::loadView('pdf.spr', compact('booking', 'settings'));
        
        return $pdf->stream("SPR-{$booking->spk_number}.pdf");
    }
}
