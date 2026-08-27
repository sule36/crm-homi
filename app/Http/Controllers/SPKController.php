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
        $safeName = str_replace(['/', '\\', ' '], '_', $booking->spk_number);

        $pdf = Pdf::loadView('pdf.spr', compact('booking', 'settings'))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('chroot', [public_path(), storage_path()]);
        
        return $pdf->download("SPR-{$safeName}.pdf");
    }

    public function stream(Booking $booking)
    {
        $booking->load(['unit.project', 'unit.unitType', 'lead', 'bookedBy', 'paymentSchedules']);
        $settings = $this->getSettings();

        if (request()->has('html') || request()->query('view') === 'html' || !request()->has('pdf')) {
            return view('pdf.spr', compact('booking', 'settings'));
        }

        $safeName = str_replace(['/', '\\', ' '], '_', $booking->spk_number);

        $pdf = Pdf::loadView('pdf.spr', compact('booking', 'settings'))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('chroot', [public_path(), storage_path()]);
        
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"SPR-{$safeName}.pdf\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
