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
        $brokers = BrokerCompany::withCount('agents')
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"))
            ->when($request->status, fn($q, $st) => $q->where('status', $st))
            ->latest()
            ->paginate(15);

        $agents = User::with(['brokerCompany', 'roles'])
            ->when($request->agent_type, fn($q, $type) => $q->where('agent_type', $type))
            ->when($request->search_agent, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->role(['sales_agent', 'broker', 'sales_manager'])
            ->latest()
            ->paginate(20, ['*'], 'agents_page');

        return Inertia::render('Agents/Index', [
            'brokers' => $brokers,
            'agents' => $agents,
            'brokerList' => BrokerCompany::where('status', 'active')->select('id', 'name', 'code', 'commission_rate')->get(),
            'defaultRates' => \App\Models\Setting::get('default_commission_rates', [
                'inhouse' => 2.5,
                'agency' => 3.0,
                'independent' => 3.0,
            ]),
            'filters' => $request->only(['search', 'status', 'agent_type', 'search_agent']),
        ]);
    }

    public function store(Request $request)
    {
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
        ]);

        $broker = BrokerCompany::create($validated);

        AuditLog::record('broker_created', $broker, null, $broker->toArray());

        return back()->with('success', 'Kantor agen / Broker company berhasil ditambahkan.');
    }

    public function update(Request $request, BrokerCompany $broker)
    {
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
        ]);

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
