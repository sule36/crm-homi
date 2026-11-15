<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SPKController extends Controller
{
    private function getSettingsForBooking(Booking $booking): array
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

    private function prepareBookingForSpr(Booking $booking): array
    {
        $relations = ['unit.project', 'unit.unitType', 'lead', 'bookedBy', 'paymentSchedules', 'reservation'];
        if (Schema::hasColumn('bookings', 'bank_account_id')) {
            $relations[] = 'bankAccount';
        }
        $booking->load($relations);

        // Auto-heal / auto-sync customer name with lead if profile was updated and no secondary signer
        if (empty($booking->secondary_name) && $booking->lead && Schema::hasColumn('bookings', 'sig4_name')) {
            try {
                if ($booking->sig4_name && $booking->sig4_name !== $booking->lead->name) {
                    $healData = ['sig4_name' => $booking->lead->name];
                    if (Schema::hasColumn('bookings', 'sig4_image')) {
                        $healData['sig4_image'] = null;
                    }
                    if (Schema::hasColumn('bookings', 'customer_signed_at')) {
                        $healData['customer_signed_at'] = null;
                    }
                    $booking->update($healData);
                } elseif (empty($booking->sig4_name)) {
                    $booking->update(['sig4_name' => $booking->lead->name]);
                }
            } catch (\Throwable $e) {
                Log::warning("SPK auto-heal sig4_name failed for booking #{$booking->id}: " . $e->getMessage());
            }
        }

        $settings = $this->getSettingsForBooking($booking);
        $rawSpk = (string)($booking->spk_number ?: ('SPR-' . $booking->id));
        $safeName = str_replace(['/', '\\', ' '], '_', $rawSpk);

        return compact('settings', 'safeName');
    }

    private function renderSprPdf(Booking $booking, array $settings)
    {
        return Pdf::loadView('pdf.spr', compact('booking', 'settings'))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('chroot', [public_path(), storage_path()]);
    }

    public function download(Booking $booking)
    {
        $prepared = $this->prepareBookingForSpr($booking);
        $pdf = $this->renderSprPdf($booking, $prepared['settings']);

        return $pdf->download("SPR-{$prepared['safeName']}.pdf");
    }

    public function stream(Booking $booking)
    {
        if (!auth()->check()) {
            $token = request()->query('token');
            if (!$token || $token !== $booking->tracking_token) {
                abort(403, 'Akses dokumen SPR tidak diizinkan. Token pelacakan tidak valid.');
            }
        }

        if (request()->has('download')) {
            return $this->download($booking);
        }

        $prepared = $this->prepareBookingForSpr($booking);
        $settings = $prepared['settings'];

        if (request()->has('html') || request()->query('view') === 'html' || !request()->has('pdf')) {
            return response(view('pdf.spr', compact('booking', 'settings')))
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        }

        $pdf = $this->renderSprPdf($booking, $settings);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"SPR-{$prepared['safeName']}.pdf\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
