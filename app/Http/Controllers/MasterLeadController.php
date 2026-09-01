<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\BrokerCompany;
use App\Models\Commission;
use App\Models\Booking;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class MasterLeadController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isMasterLead = $user->hasRole('master_lead') || $user->agent_type === 'master_lead';

        // 1. Query Master Lead Partners with Sub-Agents
        $mlQuery = User::withCount(['subAgents'])
            ->with(['subAgents' => function ($q) {
                $q->withCount(['commissions'])
                  ->withSum('commissions', 'amount')
                  ->with('brokerCompany');
            }])
            ->where(function ($q) {
                $q->where('agent_type', 'master_lead')
                  ->orWhereHas('roles', fn($rq) => $rq->where('name', 'master_lead'));
            });

        if ($isMasterLead) {
            $mlQuery->where('id', $user->id);
        }

        $masterLeads = $mlQuery
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"))
            ->latest()
            ->paginate(15, ['*'], 'ml_page')
            ->through(function ($ml) {
                $teamUserIds = User::where('master_lead_id', $ml->id)->pluck('id')->push($ml->id);

                $totalBookings = Booking::whereIn('booked_by', $teamUserIds)
                    ->whereIn('status', ['approved', 'completed', 'booked'])
                    ->count();

                $totalRevenue = Booking::whereIn('booked_by', $teamUserIds)
                    ->whereIn('status', ['approved', 'completed', 'booked'])
                    ->sum('final_price');

                $overridingCommissions = Commission::where('user_id', $ml->id)
                    ->where('payout_recipient', 'master_lead')
                    ->sum('amount');

                return [
                    'id' => $ml->id,
                    'name' => $ml->name,
                    'email' => $ml->email,
                    'phone' => $ml->phone,
                    'commission_rate' => $ml->commission_rate ?: \App\Models\Setting::get('default_commission_rates.master_lead_overriding', 4.5),
                    'status' => $ml->status,
                    'bank_name' => $ml->bank_name,
                    'bank_account_number' => $ml->bank_account_number,
                    'bank_account_name' => $ml->bank_account_name,
                    'sub_agents_count' => $ml->sub_agents_count,
                    'total_bookings' => $totalBookings,
                    'total_revenue' => (float)$totalRevenue,
                    'overriding_commissions' => (float)$overridingCommissions,
                    'sub_agents' => $ml->subAgents->map(function ($sa) {
                        $saBookingsCount = Booking::where('booked_by', $sa->id)
                            ->whereIn('status', ['approved', 'completed', 'booked'])
                            ->count();
                        $saRevenue = Booking::where('booked_by', $sa->id)
                            ->whereIn('status', ['approved', 'completed', 'booked'])
                            ->sum('final_price');

                        return [
                            'id' => $sa->id,
                            'name' => $sa->name,
                            'email' => $sa->email,
                            'phone' => $sa->phone,
                            'agent_type' => $sa->agent_type,
                            'commission_rate' => $sa->commission_rate,
                            'broker_company_id' => $sa->broker_company_id,
                            'broker_company_name' => $sa->brokerCompany?->name,
                            'broker_company_code' => $sa->brokerCompany?->code,
                            'bank_name' => $sa->bank_name,
                            'bank_account_number' => $sa->bank_account_number,
                            'bank_account_name' => $sa->bank_account_name,
                            'effective_bank_account' => $sa->effective_bank_account,
                            'total_bookings' => $saBookingsCount,
                            'total_revenue' => (float)$saRevenue,
                            'total_commissions' => (float)($sa->commissions_sum_amount ?? 0),
                        ];
                    }),
                    'created_at' => $ml->created_at->format('d M Y'),
                ];
            });

        // 2. Query Broker Companies (Kantor Agency)
        $brokerQuery = BrokerCompany::withCount(['agents', 'commissions'])
            ->withSum('commissions', 'amount')
            ->withSum(['commissions as paid_commissions_sum' => fn($q) => $q->where('status', 'paid')], 'amount')
            ->withSum(['commissions as pending_commissions_sum' => fn($q) => $q->where('status', 'unpaid')], 'amount');

        if ($isMasterLead) {
            $brokerQuery->where('master_lead_id', $user->id);
        }

        $brokers = $brokerQuery
            ->when($request->search_agency, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"))
            ->latest()
            ->paginate(15, ['*'], 'brokers_page');

        // 3. Query All Flat Agents
        $agentsQuery = User::with(['brokerCompany', 'roles', 'masterLead'])
            ->where(function ($q) {
                $q->whereNotNull('agent_type')
                  ->orWhereHas('roles', fn($rq) => $rq->whereIn('name', ['sales_agent', 'broker', 'sales_manager', 'agent', 'master_lead']));
            });

        if ($isMasterLead) {
            $agentsQuery->where('master_lead_id', $user->id);
        }

        $allAgents = $agentsQuery
            ->when($request->search_agent, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20, ['*'], 'agents_page');

        // 4. Ledger & Sub-Agent Payout Query
        $ledgerQuery = Commission::with(['user.masterLead', 'user.brokerCompany', 'booking.lead', 'booking.unit.project'])
            ->whereHas('booking')
            ->whereHas('user', function($q) use ($isMasterLead, $user) {
                $q->whereNotNull('master_lead_id');
                if ($isMasterLead) {
                    $q->where('master_lead_id', $user->id);
                }
            });

        $subAgentCommissions = (clone $ledgerQuery)
            ->when($request->search_ledger, function ($q, $s) {
                $q->whereHas('user', fn($uq) => $uq->where('name', 'like', "%{$s}%"))
                  ->orWhereHas('booking.lead', fn($lq) => $lq->where('name', 'like', "%{$s}%"))
                  ->orWhere('ml_receipt_number', 'like', "%{$s}%");
            })
            ->latest()
            ->paginate(15, ['*'], 'ledger_page');

        // Cashflow stats for Master Lead
        $allMlOverridingCommissions = Commission::where('payout_recipient', 'master_lead')
            ->when($isMasterLead, fn($q) => $q->where('user_id', $user->id))
            ->sum('amount');

        $subAgentTotalPotency = (clone $ledgerQuery)->sum('amount');
        $subAgentPaidOutflow = (clone $ledgerQuery)->where('ml_payout_status', 'paid')->sum('amount');
        $subAgentPendingOutflow = (clone $ledgerQuery)->where(fn($q) => $q->where('ml_payout_status', 'unpaid')->orWhereNull('ml_payout_status'))->sum('amount');

        // Stats summary
        $totalMasterLeads = User::where('agent_type', 'master_lead')->orWhereHas('roles', fn($q) => $q->where('name', 'master_lead'))->count();
        $totalSubAgents = User::whereNotNull('master_lead_id')->count();
        $totalBrokers = BrokerCompany::count();
        $totalMasterLeadRevenue = Booking::whereHas('bookedBy', fn($q) => $q->whereNotNull('master_lead_id')->orWhere('agent_type', 'master_lead'))
            ->whereIn('status', ['approved', 'completed', 'booked'])
            ->sum('final_price');

        $mlOverridingCommissions = Commission::with(['user', 'booking.lead', 'booking.unit.project'])
            ->where('payout_recipient', 'master_lead')
            ->whereHas('booking')
            ->when($isMasterLead, fn($q) => $q->where('user_id', $user->id))
            ->latest()
            ->get();

        return Inertia::render('MasterLeads/Index', [
            'masterLeads' => $masterLeads,
            'brokers' => $brokers,
            'allAgents' => $allAgents,
            'subAgentCommissions' => $subAgentCommissions,
            'mlOverridingCommissions' => $mlOverridingCommissions,
            'brokerList' => BrokerCompany::where('status', 'active')->select('id', 'name', 'code', 'commission_rate')->get(),
            'masterLeadList' => User::where('agent_type', 'master_lead')->orWhereHas('roles', fn($q) => $q->where('name', 'master_lead'))->select('id', 'name', 'phone')->get(),
            'stats' => [
                'total_master_leads' => $totalMasterLeads,
                'total_sub_agents' => $totalSubAgents,
                'total_brokers' => $totalBrokers,
                'total_revenue' => (float)$totalMasterLeadRevenue,
                'net_overriding_ml' => (float)$allMlOverridingCommissions,
                'sub_agent_total_potency' => (float)$subAgentTotalPotency,
                'sub_agent_paid_outflow' => (float)$subAgentPaidOutflow,
                'sub_agent_pending_outflow' => (float)$subAgentPendingOutflow,
                'default_overriding_rate' => \App\Models\Setting::get('default_commission_rates.master_lead_overriding', 4.5),
            ],
            'filters' => $request->only(['search', 'search_agency', 'search_agent', 'search_ledger']),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'phone' => 'nullable|string|max:20',
                'password' => 'required|string|min:6',
                'commission_rate' => 'nullable|numeric|min:0|max:100',
                'bank_name' => 'nullable|string|max:100',
                'bank_account_number' => 'nullable|string|max:100',
                'bank_account_name' => 'nullable|string|max:255',
            ]);

            $plainPassword = $request->password;

            $companyId = auth()->user()?->company_id;
            if (!$companyId && \App\Models\Company::exists()) {
                $companyId = \App\Models\Company::first()->id;
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'company_id' => $companyId,
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($plainPassword),
                'agent_type' => 'master_lead',
                'commission_rate' => (is_numeric($request->commission_rate) && $request->commission_rate > 0) ? (float)$request->commission_rate : 4.5,
                'bank_name' => $validated['bank_name'] ?? null,
                'bank_account_number' => $validated['bank_account_number'] ?? null,
                'bank_account_name' => $validated['bank_account_name'] ?? null,
                'status' => 'active',
            ]);

            try {
                \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'master_lead', 'guard_name' => 'web']);
                $user->assignRole('master_lead');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Spatie role assign fallback: ' . $e->getMessage());
            }

            try {
                AuditLog::record('master_lead_created', $user, null, $user->toArray());
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AuditLog record fallback: ' . $e->getMessage());
            }

            $loginUrl = url('/login');

            return back()->with('success', 'Akun Master Lead baru berhasil dibuat.')
                ->with('new_credentials', [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'password' => $plainPassword,
                    'login_url' => $loginUrl,
                ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('MasterLeadController store failed: ' . $e->getMessage());
            return back()->with('error', 'Gagal membuat Master Lead: ' . $e->getMessage());
        }
    }

    public function update(Request $request, User $user)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20',
                'password' => 'nullable|string|min:6',
                'commission_rate' => 'nullable|numeric|min:0|max:100',
                'status' => 'required|in:active,inactive',
                'bank_name' => 'nullable|string|max:100',
                'bank_account_number' => 'nullable|string|max:100',
                'bank_account_name' => 'nullable|string|max:255',
            ]);

            $data = [
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'status' => $validated['status'],
                'commission_rate' => (is_numeric($request->commission_rate) && $request->commission_rate > 0) ? (float)$request->commission_rate : $user->commission_rate,
                'bank_name' => $validated['bank_name'] ?? null,
                'bank_account_number' => $validated['bank_account_number'] ?? null,
                'bank_account_name' => $validated['bank_account_name'] ?? null,
            ];

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $old = $user->toArray();
            $user->update($data);

            try {
                AuditLog::record('master_lead_updated', $user, $old, $user->toArray());
            } catch (\Throwable $e) {}

            return back()->with('success', 'Data Master Lead berhasil diperbarui.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('MasterLeadController update failed: ' . $e->getMessage());
            return back()->with('error', 'Gagal memperbarui Master Lead: ' . $e->getMessage());
        }
    }

    public function destroy(User $user)
    {
        try {
            if ($user->id === auth()->id()) {
                return back()->with('error', 'Tidak bisa menghapus akun Anda sendiri.');
            }

            try {
                AuditLog::record('master_lead_deleted', $user, $user->toArray(), null);
            } catch (\Throwable $e) {}

            // Disassociate sub agents
            User::where('master_lead_id', $user->id)->update(['master_lead_id' => null]);
            $user->delete();

            return back()->with('success', 'Akun Master Lead berhasil dihapus.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('MasterLeadController destroy failed: ' . $e->getMessage());
            return back()->with('error', 'Gagal menghapus Master Lead: ' . $e->getMessage());
        }
    }

    public function paySubAgentCommission(Request $request, Commission $commission)
    {
        try {
            $validated = $request->validate([
                'receipt_number' => 'nullable|string|max:100',
                'notes' => 'nullable|string|max:500',
            ]);

            $receipt = $validated['receipt_number'] ?: ('TRF-ML-' . strtoupper(uniqid()));

            $commission->update([
                'ml_payout_status' => 'paid',
                'ml_paid_at' => now(),
                'ml_receipt_number' => $receipt,
                'notes' => $validated['notes'] ?? $commission->notes,
            ]);

            try {
                AuditLog::record('master_lead_sub_agent_paid', $commission, null, $commission->toArray());
            } catch (\Throwable $e) {}

            return back()->with('success', "Penyaluran komisi ke Sub-Agent berhasil dicatat. No. Ref: {$receipt}");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('MasterLeadController paySubAgentCommission failed: ' . $e->getMessage());
            return back()->with('error', 'Gagal mencatat transfer ke Sub-Agent: ' . $e->getMessage());
        }
    }
}
