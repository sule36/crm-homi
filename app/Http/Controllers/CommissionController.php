<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\User;
use App\Models\BrokerCompany;
use App\Models\Setting;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $commissions = Commission::with(['user.brokerCompany', 'brokerCompany', 'booking.lead', 'booking.unit.project'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->payout_recipient, fn($q) => $q->where('payout_recipient', $request->payout_recipient))
            ->when($request->broker_company_id, fn($q) => $q->where('broker_company_id', $request->broker_company_id))
            ->when($request->search, function ($q, $s) {
                $q->whereHas('user', fn($userQuery) => $userQuery->where('name', 'like', "%{$s}%"))
                  ->orWhereHas('booking.lead', fn($leadQuery) => $leadQuery->where('name', 'like', "%{$s}%"))
                  ->orWhere('receipt_number', 'like', "%{$s}%");
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total_pending' => Commission::where('status', 'pending')->sum('amount'),
            'total_paid' => Commission::where('status', 'paid')->sum('amount'),
            'this_month' => Commission::whereMonth('created_at', now()->month)->sum('amount'),
            'agency_commissions' => Commission::where('payout_recipient', 'agency')->sum('amount'),
            'inhouse_commissions' => Commission::whereHas('user', fn($q) => $q->where('agent_type', 'inhouse'))->sum('amount'),
            'independent_commissions' => Commission::whereHas('user', fn($q) => $q->where('agent_type', 'independent'))->sum('amount'),
        ];

        $defaultRates = Setting::get('default_commission_rates', [
            'inhouse' => 2.5,
            'agency' => 3.0,
            'independent' => 3.0,
        ]);

        return Inertia::render('Commissions/Index', [
            'commissions' => $commissions,
            'stats' => $stats,
            'brokerCompanies' => BrokerCompany::where('status', 'active')->select('id', 'name', 'code')->get(),
            'defaultRates' => $defaultRates,
            'filters' => $request->only(['status', 'payout_recipient', 'broker_company_id', 'search']),
        ]);
    }

    public function updateParameters(Request $request)
    {
        $request->validate([
            'inhouse_rate' => 'required|numeric|min:0|max:100',
            'agency_rate' => 'required|numeric|min:0|max:100',
            'independent_rate' => 'required|numeric|min:0|max:100',
        ]);

        Setting::set('default_commission_rates', [
            'inhouse' => (float)$request->inhouse_rate,
            'agency' => (float)$request->agency_rate,
            'independent' => (float)$request->independent_rate,
        ]);

        AuditLog::record('commission_parameters_updated', null, null, [
            'rates' => $request->only(['inhouse_rate', 'agency_rate', 'independent_rate'])
        ]);

        return back()->with('success', 'Parameter komisi bawaan sistem berhasil diperbarui.');
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

            // Update booking commission status
            if ($commission->booking) {
                $commission->booking->update(['commission_status' => 'paid']);
            }

            AuditLog::record('commission_paid', $commission, null, $commission->toArray());

            return back()->with('success', "Komisi berhasil dibayarkan. No. Kwitansi: $receiptNumber");
        });
    }
}
