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

        // Auto-heal / auto-sync customer name with lead if profile was updated and no secondary signer
        if (empty($booking->secondary_name) && $booking->lead) {
            if ($booking->sig4_name && $booking->sig4_name !== $booking->lead->name) {
                $booking->update([
                    'sig4_name' => $booking->lead->name,
                    'sig4_image' => null,
                    'customer_signed_at' => null,
                ]);
            } elseif (empty($booking->sig4_name)) {
                $booking->update(['sig4_name' => $booking->lead->name]);
            }
        }

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
        if (!auth()->check()) {
            $token = request()->query('token');
            if (!$token || $token !== $booking->tracking_token) {
                abort(403, 'Akses dokumen SPR tidak diizinkan. Token pelacakan tidak valid.');
            }
        }

        $relations = ['unit.project', 'unit.unitType', 'lead', 'bookedBy', 'paymentSchedules'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('bookings', 'bank_account_id')) {
            $relations[] = 'bankAccount';
        }
        $booking->load($relations);

        // Auto-heal / auto-sync customer name with lead if profile was updated and no secondary signer
        if (empty($booking->secondary_name) && $booking->lead) {
            if ($booking->sig4_name && $booking->sig4_name !== $booking->lead->name) {
                $booking->update([
                    'sig4_name' => $booking->lead->name,
                    'sig4_image' => null,
                    'customer_signed_at' => null,
                ]);
            } elseif (empty($booking->sig4_name)) {
                $booking->update(['sig4_name' => $booking->lead->name]);
            }
        }

        $settings = $this->getSettingsForBooking($booking);

        if (request()->has('download')) {
            return $this->download($booking);
        }

        if (request()->has('html') || request()->query('view') === 'html' || !request()->has('pdf')) {
            return response(view('pdf.spr', compact('booking', 'settings')))
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
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
