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
        $payoutThresholdPercent = (float) Setting::get('commission_payout_threshold_percent', 25.0);

        $commissions = Commission::with(['user.brokerCompany', 'brokerCompany', 'booking.lead', 'booking.unit.project', 'booking.transactions', 'bankAccount'])
            ->whereIn('payout_recipient', ['master_lead', 'agency', 'agent'])
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

        $commissions->getCollection()->transform(function ($comm) use ($payoutThresholdPercent) {
            $buyerPaidAmount = $comm->booking?->transactions?->sum('amount') ?? 0;
            $finalPrice = $comm->booking?->final_price > 0 ? $comm->booking->final_price : ($comm->booking?->unit_price ?? 0);
            $buyerPaidPercent = $finalPrice > 0 ? round(($buyerPaidAmount / $finalPrice) * 100, 1) : 0;
            $isEligible = $buyerPaidPercent >= $payoutThresholdPercent;

            $comm->buyer_paid_amount = $buyerPaidAmount;
            $comm->booking_final_price = $finalPrice;
            $comm->buyer_paid_percent = $buyerPaidPercent;
            $comm->payout_threshold_percent = $payoutThresholdPercent;
            $comm->is_payout_eligible = $isEligible;

            // Payout amount for Developer: Gross Overriding Fee for Master Lead, or direct amount for others
            $comm->display_payout_amount = ($comm->payout_recipient === 'master_lead' && $comm->base_commission > 0)
                ? $comm->base_commission
                : $comm->amount;

            return $comm;
        });

        $stats = [
            'total_pending' => Commission::whereIn('payout_recipient', ['master_lead', 'agency', 'agent'])->where('status', 'pending')->get()->sum(fn($c) => ($c->payout_recipient === 'master_lead' && $c->base_commission > 0) ? $c->base_commission : $c->amount),
            'total_paid' => Commission::whereIn('payout_recipient', ['master_lead', 'agency', 'agent'])->where('status', 'paid')->get()->sum(fn($c) => ($c->payout_recipient === 'master_lead' && $c->base_commission > 0) ? $c->base_commission : $c->amount),
            'this_month' => Commission::whereIn('payout_recipient', ['master_lead', 'agency', 'agent'])->whereMonth('created_at', now()->month)->get()->sum(fn($c) => ($c->payout_recipient === 'master_lead' && $c->base_commission > 0) ? $c->base_commission : $c->amount),
            'agency_commissions' => Commission::where('payout_recipient', 'agency')->sum('amount'),
            'inhouse_commissions' => Commission::whereHas('user', fn($q) => $q->whereIn('agent_type', ['inhouse', 'inhouse_developer', 'inhouse_master_lead']))->sum('amount'),
            'independent_commissions' => Commission::whereHas('user', fn($q) => $q->where('agent_type', 'independent'))->sum('amount'),
            'master_lead_commissions' => Commission::where('payout_recipient', 'master_lead')->get()->sum(fn($c) => $c->base_commission > 0 ? $c->base_commission : $c->amount),
            'eligible_count' => $commissions->getCollection()->filter(fn($c) => $c->status === 'pending' && $c->is_payout_eligible)->count(),
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

        $bankAccounts = \App\Models\BankAccount::where('is_active', true)->get();

        return Inertia::render('Commissions/Index', [
            'commissions' => $commissions,
            'stats' => $stats,
            'brokerCompanies' => BrokerCompany::where('status', 'active')->select('id', 'name', 'code')->get(),
            'defaultRates' => $defaultRates,
            'schemaConfig' => $schemaConfig,
            'masterLeads' => $masterLeads,
            'bankAccounts' => $bankAccounts,
            'payoutThresholdPercent' => $payoutThresholdPercent,
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
            'payout_threshold_percent' => 'nullable|numeric|min:0|max:100',
            'enable_master_lead' => 'nullable|boolean',
            'enable_inhouse_developer' => 'nullable|boolean',
            'enable_inhouse_master_lead' => 'nullable|boolean',
        ]);

        $newMasterRate = (float)($request->master_lead_overriding_rate ?? 4.5);

        Setting::set('default_commission_rates', [
            'inhouse_developer' => (float)($request->inhouse_developer_rate ?? 1.0),
            'inhouse_master_lead' => (float)($request->inhouse_master_lead_rate ?? 1.5),
            'master_lead_overriding' => $newMasterRate,
            'agency' => (float)($request->agency_rate ?? 3.0),
            'independent' => (float)($request->independent_rate ?? 2.5),
        ]);

        Setting::set('commission_payout_threshold_percent', (float)($request->payout_threshold_percent ?? 25.0));

        Setting::set('commission_schema_config', [
            'enable_master_lead' => (bool)$request->enable_master_lead,
            'enable_inhouse_developer' => (bool)$request->enable_inhouse_developer,
            'enable_inhouse_master_lead' => (bool)$request->enable_inhouse_master_lead,
        ]);

        // Sync Master Lead Users commission_rate & recalculate pending ML overriding commissions
        User::where('agent_type', 'master_lead')
            ->orWhereHas('roles', fn($q) => $q->where('name', 'master_lead'))
            ->update(['commission_rate' => $newMasterRate]);

        $pendingMlCommissions = Commission::with(['booking.bookedBy'])
            ->where('payout_recipient', 'master_lead')
            ->where('status', 'pending')
            ->get();

        foreach ($pendingMlCommissions as $comm) {
            $bk = $comm->booking;
            if (!$bk) continue;
            $finalPrice = $bk->final_price > 0 ? $bk->final_price : ($bk->unit_price ?? 0);
            $subAgentRate = $bk->bookedBy?->effective_commission_rate ?? 3.0;
            $baseCommission = $finalPrice * ($subAgentRate / 100);
            $masterTotalGross = $finalPrice * ($newMasterRate / 100);
            $masterNetFee = max(0, $masterTotalGross - $baseCommission);

            $comm->update([
                'rate_used' => $newMasterRate,
                'base_commission' => $masterTotalGross,
                'amount' => $masterNetFee,
            ]);
        }

        AuditLog::record('commission_parameters_updated', null, null, [
            'rates' => $request->all()
        ]);

        return back()->with('success', 'Skema komisi, ambang batas pencairan (threshold %), & sakelar Master Lead berhasil diperbarui.');
    }

    public function pay(Request $request, Commission $commission)
    {
        if ($commission->status === 'paid') {
            return back()->with('error', 'Komisi ini sudah dibayarkan.');
        }

        $validated = $request->validate([
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'notes' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($commission, $validated) {
            $receiptNumber = 'COM-' . strtoupper(uniqid());
            
            $commission->update([
                'status' => 'paid',
                'paid_at' => now(),
                'receipt_number' => $receiptNumber,
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'notes' => $validated['notes'] ?? $commission->notes,
            ]);

            // Update booking commission status
            if ($commission->booking) {
                $commission->booking->update(['commission_status' => 'paid']);
            }

            AuditLog::record('commission_paid', $commission, null, $commission->toArray());

            return back()->with('success', "Komisi berhasil dibayarkan dan dicatat ke Buku Besar. No. Kwitansi: $receiptNumber");
        });
    }
}
