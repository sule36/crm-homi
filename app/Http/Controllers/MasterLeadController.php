<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        // Query Master Lead Users
        $query = User::withCount(['subAgents'])
            ->with(['subAgents' => fn($q) => $q->withCount('commissions')->with('brokerCompany')])
            ->where(function ($q) {
                $q->where('agent_type', 'master_lead')
                  ->orWhereHas('roles', fn($rq) => $rq->where('name', 'master_lead'));
            });

        if ($isMasterLead) {
            $query->where('id', $user->id);
        }

        $masterLeads = $query
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"))
            ->latest()
            ->paginate(15)
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
                    'sub_agents' => $ml->subAgents->map(fn($sa) => [
                        'id' => $sa->id,
                        'name' => $sa->name,
                        'email' => $sa->email,
                        'phone' => $sa->phone,
                        'agent_type' => $sa->agent_type,
                        'commission_rate' => $sa->commission_rate,
                        'broker_company_name' => $sa->brokerCompany?->name,
                    ]),
                    'created_at' => $ml->created_at->format('d M Y'),
                ];
            });

        $totalMasterLeads = User::where('agent_type', 'master_lead')->orWhereHas('roles', fn($q) => $q->where('name', 'master_lead'))->count();
        $totalSubAgents = User::whereNotNull('master_lead_id')->count();
        $totalMasterLeadRevenue = Booking::whereHas('bookedBy', fn($q) => $q->whereNotNull('master_lead_id')->orWhere('agent_type', 'master_lead'))
            ->whereIn('status', ['approved', 'completed', 'booked'])
            ->sum('final_price');

        return Inertia::render('MasterLeads/Index', [
            'masterLeads' => $masterLeads,
            'stats' => [
                'total_master_leads' => $totalMasterLeads,
                'total_sub_agents' => $totalSubAgents,
                'total_revenue' => (float)$totalMasterLeadRevenue,
                'default_overriding_rate' => \App\Models\Setting::get('default_commission_rates.master_lead_overriding', 4.5),
            ],
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
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

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($plainPassword),
            'agent_type' => 'master_lead',
            'commission_rate' => (is_numeric($request->commission_rate) && $request->commission_rate > 0) ? (float)$request->commission_rate : 4.5,
            'bank_name' => $validated['bank_name'] ?? null,
            'bank_account_number' => $validated['bank_account_number'] ?? null,
            'bank_account_name' => $validated['bank_account_name'] ?? null,
            'status' => 'active',
        ]);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'master_lead', 'guard_name' => 'web']);
        $user->assignRole('master_lead');

        AuditLog::record('master_lead_created', $user, null, $user->toArray());

        $loginUrl = url('/login');

        return back()->with('success', 'Akun Master Lead baru berhasil dibuat.')
            ->with('new_credentials', [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'password' => $plainPassword,
                'login_url' => $loginUrl,
            ]);
    }

    public function update(Request $request, User $user)
    {
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

        AuditLog::record('master_lead_updated', $user, $old, $user->toArray());

        return back()->with('success', 'Data Master Lead berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menghapus akun Anda sendiri.');
        }

        AuditLog::record('master_lead_deleted', $user, $user->toArray(), null);

        // Disassociate sub agents
        User::where('master_lead_id', $user->id)->update(['master_lead_id' => null]);
        $user->delete();

        return back()->with('success', 'Akun Master Lead berhasil dihapus.');
    }
}
