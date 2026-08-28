<?php

namespace App\Traits;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::creating(function ($model) {
            if (empty($model->company_id) && auth()->check() && auth()->user()->company_id) {
                $model->company_id = auth()->user()->company_id;
            }
        });

        static::addGlobalScope('company', function (Builder $builder) {
            if (auth()->check()) {
                $user = auth()->user();
                // Super Admin can view all unless explicitly filtered
                if (!$user->hasRole('super_admin') && $user->company_id) {
                    $builder->where($builder->getQuery()->from . '.company_id', $user->company_id);
                }
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
