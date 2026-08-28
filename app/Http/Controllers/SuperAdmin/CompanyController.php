<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::withCount(['users', 'projects', 'leads', 'bookings'])
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total_companies' => Company::count(),
            'active_companies' => Company::where('status', 'active')->count(),
            'trial_companies' => Company::where('status', 'trial')->count(),
            'total_users' => User::count(),
        ];

        return Inertia::render('SuperAdmin/Companies/Index', [
            'companies' => $companies,
            'filters' => $request->only(['search', 'status']),
            'stats' => $stats,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'subscription_plan' => 'required|in:starter,pro,enterprise',
            'status' => 'required|in:active,trial,suspended',
            'max_users' => 'required|integer|min:1',
            'max_projects' => 'required|integer|min:1',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:8',
        ]);

        $slug = Str::slug($validated['name']);
        $count = Company::where('slug', 'like', "{$slug}%")->count();
        if ($count > 0) {
            $slug .= '-' . ($count + 1);
        }

        $company = Company::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'subscription_plan' => $validated['subscription_plan'],
            'status' => $validated['status'],
            'max_users' => $validated['max_users'],
            'max_projects' => $validated['max_projects'],
            'expires_at' => $validated['status'] === 'trial' ? now()->addDays(30) : null,
        ]);

        // Create Tenant Admin User
        $adminRole = Role::firstOrCreate(['name' => 'project_manager', 'guard_name' => 'web']);
        $admin = User::create([
            'name' => $validated['admin_name'],
            'email' => $validated['admin_email'],
            'password' => bcrypt($validated['admin_password']),
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $admin->assignRole('project_manager');

        AuditLog::record('company_registered', $company, null, ['company' => $company->name, 'admin' => $admin->email]);

        return back()->with('success', "Developer/Perusahaan SaaS '{$company->name}' berhasil mendaftar! Akun Admin: {$admin->email}");
    }

    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'subscription_plan' => 'required|in:starter,pro,enterprise',
            'status' => 'required|in:active,trial,suspended',
            'max_users' => 'required|integer|min:1',
            'max_projects' => 'required|integer|min:1',
        ]);

        $old = $company->toArray();
        $company->update($validated);

        AuditLog::record('company_updated', $company, $old, $validated);

        return back()->with('success', "Perusahaan '{$company->name}' berhasil diperbarui.");
    }

    public function destroy(Company $company)
    {
        AuditLog::record('company_deleted', $company, $company->toArray());
        $company->delete();

        return back()->with('success', "Developer/Perusahaan SaaS '{$company->name}' berhasil dihapus.");
    }
}
