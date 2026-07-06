<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $commissions = Commission::with(['user', 'booking.lead', 'booking.unit'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(15);

        $stats = [
            'total_pending' => Commission::where('status', 'pending')->sum('amount'),
            'total_paid' => Commission::where('status', 'paid')->sum('amount'),
            'this_month' => Commission::whereMonth('created_at', now()->month)->sum('amount'),
        ];

        return Inertia::render('Commissions/Index', [
            'commissions' => $commissions,
            'stats' => $stats,
            'filters' => $request->only(['status']),
        ]);
    }

    public function pay(Request $request, Commission $commission)
    {
        if ($commission->status === 'paid') {
            return back()->with('error', 'Komisi ini sudah dibayarkan.');
        }

        return DB::transaction(function () use ($commission) {
            $receiptNumber = 'COM-' . strtoupper(uniqid());
            
            $commission->update([
                'status' => 'paid',
                'paid_at' => now(),
                'receipt_number' => $receiptNumber,
            ]);

            // Optional: Update booking state if needed
            $commission->booking->update(['commission_status' => 'paid']);

            AuditLog::record('commission_paid', $commission, null, $commission->toArray());

            return back()->with('success', "Komisi berhasil dibayarkan. No. Kwitansi: $receiptNumber");
        });
    }
}
