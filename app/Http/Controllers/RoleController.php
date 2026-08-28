<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\AuditLog;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
                'users_count' => \App\Models\User::role($role->name)->count(),
            ];
        });

        $permissions = Permission::all()->groupBy(function ($perm) {
            $parts = explode('.', $perm->name);
            return $parts[0] ?? 'general';
        });

        return Inertia::render('Settings/Roles', [
            'roles' => $roles,
            'permissionsGrouped' => $permissions,
            'allPermissions' => Permission::pluck('name')->toArray(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:roles,name',
            'permissions' => 'array',
        ]);

        $role = Role::create([
            'name' => strtolower(str_replace(' ', '_', $request->name)),
            'guard_name' => 'web',
        ]);

        if (!empty($request->permissions)) {
            $role->syncPermissions($request->permissions);
        }

        AuditLog::record('role_created', $role, null, ['name' => $role->name, 'permissions' => $request->permissions]);

        return back()->with('success', 'Role/Peran baru berhasil dibuat.');
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'permissions' => 'required|array',
        ]);

        $old = $role->permissions->pluck('name')->toArray();
        $role->syncPermissions($request->permissions);

        AuditLog::record('role_permissions_updated', $role, ['permissions' => $old], ['permissions' => $request->permissions]);

        return back()->with('success', 'Hak akses/izin peran ' . $role->name . ' berhasil diperbarui.');
    }

    public function destroy(Role $role)
    {
        if (in_array($role->name, ['super_admin', 'project_manager', 'sales_manager', 'sales_agent', 'master_lead', 'finance', 'broker'])) {
            return back()->with('error', 'Role bawaan sistem tidak dapat dihapus.');
        }

        AuditLog::record('role_deleted', $role, $role->toArray());
        $role->delete();

        return back()->with('success', 'Role berhasil dihapus.');
    }
}
