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
            'phone' => 'nullable|string|max:20',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
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
            'commission_rate' => $request->commission_rate ?? 1.00,
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
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'bank_name' => 'nullable|string',
            'bank_account_number' => 'nullable|string',
            'bank_account_name' => 'nullable|string',
        ]);

        $user->update($request->only([
            'name', 'phone', 'project_id', 'broker_company_id', 'status', 
            'commission_rate', 'bank_name', 'bank_account_number', 'bank_account_name'
        ]));
        
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
