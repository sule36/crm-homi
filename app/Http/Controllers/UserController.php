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
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', Rules\Password::defaults()],
            'role' => 'required|exists:roles,name',
            'project_id' => 'nullable|exists:projects,id',
            'broker_company_id' => 'nullable|exists:broker_companies,id',
            'agent_type' => 'nullable|in:inhouse,agency_agent,independent',
            'phone' => 'nullable|string|max:20',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'custom_bonus' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string',
            'bank_account_number' => 'nullable|string',
            'bank_account_name' => 'nullable|string',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'project_id' => $request->project_id,
            'broker_company_id' => $request->broker_company_id,
            'agent_type' => $request->agent_type ?? ($request->broker_company_id ? 'agency_agent' : 'inhouse'),
            'commission_rate' => $request->commission_rate,
            'custom_bonus' => $request->custom_bonus ?? 0,
            'bank_name' => $request->bank_name,
            'bank_account_number' => $request->bank_account_number,
            'bank_account_name' => $request->bank_account_name,
            'status' => 'active',
        ]);

        $user->assignRole($request->role);

        return back()->with('success', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|exists:roles,name',
            'project_id' => 'nullable|exists:projects,id',
            'broker_company_id' => 'nullable|exists:broker_companies,id',
            'agent_type' => 'nullable|in:inhouse,agency_agent,independent',
            'phone' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
            'password' => ['nullable', Rules\Password::defaults()],
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'custom_bonus' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string',
            'bank_account_number' => 'nullable|string',
            'bank_account_name' => 'nullable|string',
        ]);

        $data = $request->only([
            'name', 'phone', 'project_id', 'broker_company_id', 'agent_type', 'status', 
            'commission_rate', 'custom_bonus', 'bank_name', 'bank_account_number', 'bank_account_name'
        ]);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);
        
        $user->syncRoles([$request->role]);

        return back()->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) return back()->with('error', 'Tidak bisa menghapus diri sendiri.');
        $user->delete();
        return back()->with('success', 'User berhasil dihapus.');
    }
}
