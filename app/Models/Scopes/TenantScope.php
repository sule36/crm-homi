<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (!auth()->check()) {
            return;
        }

        $user = auth()->user();
        $table = $model->getTable();

        // 1. Jika user terafiliasi dengan developer (memiliki company_id)
        if ($user->company_id) {
            // Isolasi data: hanya tampilkan data milik developer yang bersangkutan
            $builder->where($table . '.company_id', $user->company_id);
            return;
        }

        // 2. Jika user adalah Super Admin SaaS (tidak memiliki company_id / pemilik platform)
        // Super Admin SaaS TIDAK BOLEH melihat data operasional developer (proyek, unit, leads, booking, kas)
        if ($user->hasRole('super_admin') || $user->email === 'admin@homi.id') {
            if ($model instanceof \App\Models\User) {
                // Izinkan query auth dan list pengguna platform oleh super admin jika diperlukan
                return;
            }
            // Isolasi penuh: Super Admin tidak melihat data operasional tenant
            $builder->whereRaw('1 = 0');
        }
    }
}
