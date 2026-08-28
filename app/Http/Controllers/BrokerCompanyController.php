<?php

namespace App\Http\Controllers;

use App\Models\BrokerCompany;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BrokerCompanyController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isMasterLead = $user && ($user->hasRole('master_lead') || $user->agent_type === 'master_lead');

        $brokers = BrokerCompany::withCount(['agents', 'commissions'])
            ->withSum('commissions', 'amount')
            ->withSum(['commissions as paid_commissions_sum' => fn($q) => $q->where('status', 'paid')], 'amount')
            ->withSum(['commissions as pending_commissions_sum' => fn($q) => $q->where('status', 'unpaid')], 'amount')
            ->with(['agents' => fn($q) => $q->withSum('commissions', 'amount')])
            ->when($isMasterLead, fn($q) => $q->where('master_lead_id', $user->id))
            ->when($request->master_lead_id, fn($q, $ml) => $q->where('master_lead_id', $ml))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"))
            ->when($request->status, fn($q, $st) => $q->where('status', $st))
            ->latest()
            ->paginate(15);

        $agents = User::with(['brokerCompany', 'roles', 'masterLead'])
            ->when($isMasterLead, fn($q) => $q->where('master_lead_id', $user->id))
            ->when($request->master_lead_id, fn($q, $ml) => $q->where('master_lead_id', $ml))
            ->when($request->agent_type, fn($q, $type) => $q->where('agent_type', $type))
            ->when($request->search_agent, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->where(function ($q) {
                $q->whereNotNull('agent_type')
                  ->orWhereHas('roles', fn($rq) => $rq->whereIn('name', ['sales_agent', 'broker', 'sales_manager', 'agent', 'master_lead']));
            })
            ->latest()
            ->paginate(20, ['*'], 'agents_page');

        $masterLeads = User::whereHas('roles', fn($q) => $q->where('name', 'master_lead'))
            ->orWhere('agent_type', 'master_lead')
            ->select('id', 'name', 'phone')
            ->get();

        return Inertia::render('Agents/Index', [
            'brokers' => $brokers,
            'agents' => $agents,
            'brokerList' => BrokerCompany::where('status', 'active')->select('id', 'name', 'code', 'commission_rate')->get(),
            'masterLeads' => $masterLeads,
            'defaultRates' => \App\Models\Setting::get('default_commission_rates', [
                'inhouse_developer' => 1.0,
                'inhouse_master_lead' => 1.5,
                'master_lead_overriding' => 4.5,
                'agency' => 3.0,
                'independent' => 2.5,
            ]),
            'filters' => $request->only(['search', 'status', 'agent_type', 'search_agent', 'master_lead_id']),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $isMasterLead = $user && ($user->hasRole('master_lead') || $user->agent_type === 'master_lead');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|in:active,inactive',
            'bank_name' => 'nullable|string',
            'bank_account_number' => 'nullable|string',
            'bank_account_name' => 'nullable|string',
            'notes' => 'nullable|string',
            'master_lead_id' => 'nullable|exists:users,id',
        ]);

        if ($isMasterLead) {
            $validated['master_lead_id'] = $user->id;
        }

        $broker = BrokerCompany::create($validated);

        AuditLog::record('broker_created', $broker, null, $broker->toArray());

        return back()->with('success', 'Kantor agen / Broker company berhasil ditambahkan.');
    }

    public function update(Request $request, BrokerCompany $broker)
    {
        $user = auth()->user();
        $isMasterLead = $user && ($user->hasRole('master_lead') || $user->agent_type === 'master_lead');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|in:active,inactive',
            'bank_name' => 'nullable|string',
            'bank_account_number' => 'nullable|string',
            'bank_account_name' => 'nullable|string',
            'notes' => 'nullable|string',
            'master_lead_id' => 'nullable|exists:users,id',
        ]);

        if ($isMasterLead) {
            $validated['master_lead_id'] = $user->id;
        }

        $oldValues = $broker->toArray();
        $broker->update($validated);

        AuditLog::record('broker_updated', $broker, $oldValues, $broker->toArray());

        return back()->with('success', 'Kantor agen berhasil diperbarui.');
    }

    public function destroy(BrokerCompany $broker)
    {
        $oldValues = $broker->toArray();

        AuditLog::record('broker_deleted', $broker, $oldValues, null);

        $broker->delete();

        return back()->with('success', 'Kantor agen berhasil dihapus.');
    }
}
