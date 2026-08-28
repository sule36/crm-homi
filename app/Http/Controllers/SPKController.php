<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SPKController extends Controller
{
    private function getSettingsForBooking(Booking $booking)
    {
        $settingsRaw = Setting::all();
        $settings = [];
        foreach ($settingsRaw as $s) {
            $settings[$s->key] = Setting::get($s->key);
        }

        if (!empty($booking->spr_terms_conditions) && is_array($booking->spr_terms_conditions)) {
            $settings['spr_terms_conditions'] = $booking->spr_terms_conditions;
        }
        if (!empty($booking->spr_bank_info) && is_array($booking->spr_bank_info)) {
            $settings['spr_bank_info'] = $booking->spr_bank_info;
        }
        if (!empty($booking->spr_special_offer) && is_array($booking->spr_special_offer)) {
            $settings['spr_special_offer'] = $booking->spr_special_offer;
        }

        return $settings;
    }

    public function download(Booking $booking)
    {
        $relations = ['unit.project', 'unit.unitType', 'lead', 'bookedBy', 'paymentSchedules'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('bookings', 'bank_account_id')) {
            $relations[] = 'bankAccount';
        }
        $booking->load($relations);
        $settings = $this->getSettingsForBooking($booking);
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
        $relations = ['unit.project', 'unit.unitType', 'lead', 'bookedBy', 'paymentSchedules'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('bookings', 'bank_account_id')) {
            $relations[] = 'bankAccount';
        }
        $booking->load($relations);
        $settings = $this->getSettingsForBooking($booking);

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
