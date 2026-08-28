<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with(['roles', 'project', 'brokerCompany'])
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->paginate(15);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => \Spatie\Permission\Models\Role::all(),
            'projects' => Project::all(),
            'broker_companies' => \App\Models\BrokerCompany::where('status', 'active')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['nullable'],
            'role' => 'nullable|string',
            'project_id' => 'nullable|exists:projects,id',
            'broker_company_id' => 'nullable|exists:broker_companies,id',
            'master_lead_id' => 'nullable|exists:users,id',
            'agent_type' => 'nullable|in:inhouse,inhouse_developer,inhouse_master_lead,agency_agent,independent,master_lead',
            'phone' => 'nullable|string|max:20',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'custom_bonus' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string',
            'bank_account_number' => 'nullable|string',
            'bank_account_name' => 'nullable|string',
        ]);

        $email = $request->email ?: 'agent_' . time() . '_' . rand(100, 999) . '@homi.id';
        $password = $request->filled('password') ? Hash::make($request->password) : Hash::make('password123');
        $role = $request->role ?: 'sales_agent';
        $agentType = $request->agent_type ?: ($request->filled('broker_company_id') ? 'agency_agent' : 'inhouse');

        $isMasterLead = auth()->user() && (auth()->user()->hasRole('master_lead') || auth()->user()->agent_type === 'master_lead');
        $masterLeadId = $isMasterLead ? auth()->id() : ($request->filled('master_lead_id') ? $request->master_lead_id : null);
        $brokerCompanyId = $request->filled('broker_company_id') ? $request->broker_company_id : null;
        $projectId = $request->filled('project_id') ? $request->project_id : null;

        $user = User::create([
            'name' => $request->name,
            'email' => $email,
            'password' => $password,
            'phone' => $request->phone,
            'project_id' => $projectId,
            'broker_company_id' => $brokerCompanyId,
            'master_lead_id' => $masterLeadId,
            'agent_type' => $agentType,
            'commission_rate' => (is_numeric($request->commission_rate) && $request->commission_rate > 0) ? (float)$request->commission_rate : 0,
            'custom_bonus' => is_numeric($request->custom_bonus) ? (float)$request->custom_bonus : 0,
            'bank_name' => $request->bank_name,
            'bank_account_number' => $request->bank_account_number,
            'bank_account_name' => $request->bank_account_name,
            'status' => 'active',
        ]);

        $assignedRole = $agentType === 'master_lead' ? 'master_lead' : ($role ?: 'sales_agent');
        if ($assignedRole) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $assignedRole, 'guard_name' => 'web']);
            $user->syncRoles([$assignedRole]);
        }

        return back()->with('success', 'Agen / Staff baru berhasil mendaftar.');
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'nullable|string',
            'project_id' => 'nullable|exists:projects,id',
            'broker_company_id' => 'nullable|exists:broker_companies,id',
            'master_lead_id' => 'nullable|exists:users,id',
            'agent_type' => 'nullable|in:inhouse,inhouse_developer,inhouse_master_lead,agency_agent,independent,master_lead',
            'phone' => 'nullable|string|max:20',
            'status' => 'nullable|in:active,inactive',
            'password' => ['nullable', Rules\Password::defaults()],
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'custom_bonus' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string',
            'bank_account_number' => 'nullable|string',
            'bank_account_name' => 'nullable|string',
        ]);

        $isMasterLead = auth()->user() && (auth()->user()->hasRole('master_lead') || auth()->user()->agent_type === 'master_lead');
        $masterLeadId = $isMasterLead ? auth()->id() : ($request->has('master_lead_id') ? ($request->filled('master_lead_id') ? $request->master_lead_id : null) : $user->master_lead_id);
        $brokerCompanyId = $request->has('broker_company_id') ? ($request->filled('broker_company_id') ? $request->broker_company_id : null) : $user->broker_company_id;
        $projectId = $request->has('project_id') ? ($request->filled('project_id') ? $request->project_id : null) : $user->project_id;

        $data = [
            'name' => $request->name,
            'phone' => $request->phone,
            'project_id' => $projectId,
            'broker_company_id' => $brokerCompanyId,
            'master_lead_id' => $masterLeadId,
            'status' => $request->status ?: $user->status,
            'bank_name' => $request->bank_name,
            'bank_account_number' => $request->bank_account_number,
            'bank_account_name' => $request->bank_account_name,
        ];

        if ($request->filled('agent_type')) {
            $data['agent_type'] = $request->agent_type;
        }

        if ($request->has('commission_rate')) {
            $data['commission_rate'] = (is_numeric($request->commission_rate) && $request->commission_rate > 0) ? (float)$request->commission_rate : null;
        }

        if ($request->has('custom_bonus')) {
            $data['custom_bonus'] = is_numeric($request->custom_bonus) ? (float)$request->custom_bonus : 0;
        }

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        $targetRole = ($request->agent_type === 'master_lead' || $user->agent_type === 'master_lead') ? 'master_lead' : ($request->role ?: null);
        if ($targetRole) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $targetRole, 'guard_name' => 'web']);
            $user->syncRoles([$targetRole]);
        }

        return back()->with('success', 'Data staff / agen berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) return back()->with('error', 'Tidak bisa menghapus diri sendiri.');
        $user->delete();
        return back()->with('success', 'User berhasil dihapus.');
    }
}
