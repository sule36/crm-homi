<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BookingDocumentController extends Controller
{
    public function store(Request $request, Booking $booking)
    {
        $request->validate([
            'type' => 'required|string|in:ktp,kk,npwp,payment_proof,other',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $file = $request->file('file');
        $path = $file->store('bookings/documents', 'public');

        BookingDocument::create([
            'booking_id' => $booking->id,
            'type' => $request->type,
            'name' => $file->getClientOriginalName(),
            'file_path' => $path,
        ]);

        return back()->with('success', 'Dokumen berhasil diunggah.');
    }

    public function destroy(BookingDocument $document)
    {
        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Dokumen dihapus.');
    }
}
