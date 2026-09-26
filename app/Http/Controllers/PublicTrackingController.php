<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PublicTrackingController extends Controller
{
    public function show($token)
    {
        $booking = Booking::with(['lead', 'unit.project', 'unit.unitType', 'paymentSchedules', 'transactions', 'bookedBy'])
            ->where('tracking_token', $token)
            ->firstOrFail();

        return Inertia::render('Public/BookingTracking', [
            'booking' => $booking,
        ]);
    }

    public function sign(Request $request, $token)
    {
        $booking = Booking::where('tracking_token', $token)->firstOrFail();

        $validated = $request->validate([
            'role' => 'required|in:customer,agent',
            'signature' => 'required|string',
            'signer_name' => 'nullable|string|max:255',
        ]);

        $data = $validated['signature'];
        $role = $validated['role'];
        $signerName = $validated['signer_name'] ?? null;

        $imagePath = null;
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
            $cleanData = substr($data, strpos($data, ',') + 1);
            $ext = strtolower($type[1]);
            $decoded = base64_decode($cleanData);
            if ($decoded !== false) {
                $filename = "signatures/booking_{$booking->id}_{$role}_" . time() . ".{$ext}";
                Storage::disk('public')->put($filename, $decoded);
                $imagePath = 'storage/' . $filename;
            }
        }

        if (!$imagePath) {
            $imagePath = $data;
        }

        if ($role === 'customer') {
            $booking->sig4_image = $imagePath;
            $booking->customer_signed_at = now();
            if (!empty($signerName)) {
                $booking->sig4_name = $signerName;
            } elseif (empty($booking->sig4_name)) {
                $booking->sig4_name = $booking->lead->name ?? 'Konsumen';
            }
            $booking->save();
        } elseif ($role === 'agent') {
            $booking->sig3_image = $imagePath;
            $booking->agent_signed_at = now();
            if (!empty($signerName)) {
                $booking->sig3_name = $signerName;
            } elseif (empty($booking->sig3_name)) {
                $booking->sig3_name = $booking->bookedBy->name ?? 'Sales Agent';
            }
            $booking->save();
        }

        return back()->with('success', 'Tanda tangan digital berhasil dibubuhkan.');
    }
}
