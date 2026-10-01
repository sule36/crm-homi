<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;

class TenantScope implements Scope
{
    private static bool $isApplying = false;

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (static::$isApplying) {
            return;
        }

        // Avoid querying Auth during unauthenticated requests or while resolving Auth::user()
        if (!auth()->hasUser()) {
            return;
        }

        $user = auth()->user();
        if (!$user) {
            return;
        }

        static::$isApplying = true;
        try {
            $table = $model->getTable();

            // 1. Jika user terafiliasi dengan developer (memiliki company_id)
            if ($user->company_id) {
                // Isolasi data: tampilkan data milik developer yang bersangkutan secara ketat (100% private).
                $builder->where($table . '.company_id', $user->company_id);
                return;
            }

            // 2. Jika user adalah Super Admin SaaS (tidak memiliki company_id / pemilik platform)
            // Super Admin SaaS TIDAK BOLEH melihat data operasional developer (proyek, unit, leads, booking, kas)
            if ($user->hasRole('super_admin') || $user->email === 'admin@homi.id') {
                if ($model instanceof \App\Models\User || $model instanceof \App\Models\Company) {
                    // Izinkan query auth dan list pengguna platform oleh super admin jika diperlukan
                    return;
                }
                // Isolasi penuh: Super Admin tidak melihat data operasional tenant
                $builder->whereRaw('1 = 0');
            }
        } finally {
            static::$isApplying = false;
        }
    }
}
