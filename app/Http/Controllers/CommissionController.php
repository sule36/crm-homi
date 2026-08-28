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
            'inhouse_commissions' => Commission::whereHas('user', fn($q) => $q->whereIn('agent_type', ['inhouse', 'inhouse_developer', 'inhouse_master_lead']))->sum('amount'),
            'independent_commissions' => Commission::whereHas('user', fn($q) => $q->where('agent_type', 'independent'))->sum('amount'),
            'master_lead_commissions' => Commission::whereHas('user', fn($q) => $q->whereIn('agent_type', ['master_lead']))->sum('amount'),
        ];

        $defaultRates = Setting::get('default_commission_rates', [
            'inhouse_developer' => 1.0,
            'inhouse_master_lead' => 1.5,
            'master_lead_overriding' => 4.5,
            'agency' => 3.0,
            'independent' => 2.5,
        ]);

        $schemaConfig = Setting::get('commission_schema_config', [
            'enable_master_lead' => true,
            'enable_inhouse_developer' => true,
            'enable_inhouse_master_lead' => true,
        ]);

        $masterLeads = User::whereHas('roles', fn($q) => $q->where('name', 'master_lead'))
            ->orWhere('agent_type', 'master_lead')
            ->select('id', 'name', 'phone')
            ->get();

        return Inertia::render('Commissions/Index', [
            'commissions' => $commissions,
            'stats' => $stats,
            'brokerCompanies' => BrokerCompany::where('status', 'active')->select('id', 'name', 'code')->get(),
            'defaultRates' => $defaultRates,
            'schemaConfig' => $schemaConfig,
            'masterLeads' => $masterLeads,
            'filters' => $request->only(['status', 'payout_recipient', 'broker_company_id', 'search']),
        ]);
    }

    public function updateParameters(Request $request)
    {
        $request->validate([
            'inhouse_developer_rate' => 'nullable|numeric|min:0|max:100',
            'inhouse_master_lead_rate' => 'nullable|numeric|min:0|max:100',
            'master_lead_overriding_rate' => 'nullable|numeric|min:0|max:100',
            'agency_rate' => 'nullable|numeric|min:0|max:100',
            'independent_rate' => 'nullable|numeric|min:0|max:100',
            'enable_master_lead' => 'nullable|boolean',
            'enable_inhouse_developer' => 'nullable|boolean',
            'enable_inhouse_master_lead' => 'nullable|boolean',
        ]);

        Setting::set('default_commission_rates', [
            'inhouse_developer' => (float)($request->inhouse_developer_rate ?? 1.0),
            'inhouse_master_lead' => (float)($request->inhouse_master_lead_rate ?? 1.5),
            'master_lead_overriding' => (float)($request->master_lead_overriding_rate ?? 4.5),
            'agency' => (float)($request->agency_rate ?? 3.0),
            'independent' => (float)($request->independent_rate ?? 2.5),
        ]);

        Setting::set('commission_schema_config', [
            'enable_master_lead' => (bool)$request->enable_master_lead,
            'enable_inhouse_developer' => (bool)$request->enable_inhouse_developer,
            'enable_inhouse_master_lead' => (bool)$request->enable_inhouse_master_lead,
        ]);

        AuditLog::record('commission_parameters_updated', null, null, [
            'rates' => $request->all()
        ]);

        return back()->with('success', 'Skema komisi & sakelar Master Lead / In-House berhasil diperbarui.');
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
