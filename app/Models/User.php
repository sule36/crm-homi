<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'avatar', 'project_id', 'broker_company_id', 'agent_type', 'status', 'is_accepting_leads', 'lead_capacity', 'settings', 'last_login_at', 'commission_rate', 'custom_bonus', 'bank_name', 'bank_account_number', 'bank_account_name'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
        ];
    }

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function brokerCompany()
    {
        return $this->belongsTo(BrokerCompany::class);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function activities()
    {
        return $this->hasMany(LeadActivity::class);
    }

    // Helpers
    public function getActiveLeadsCountAttribute(): int
    {
        return $this->leads()->whereNotIn('status', ['won', 'lost'])->count();
    }

    public function hasCapacity(): bool
    {
        return $this->active_leads_count < $this->lead_capacity;
    }

    public function getEffectiveCommissionRateAttribute(): float
    {
        if ($this->commission_rate !== null && (float)$this->commission_rate > 0) {
            return (float)$this->commission_rate;
        }

        if ($this->brokerCompany && $this->brokerCompany->commission_rate > 0) {
            return (float)$this->brokerCompany->commission_rate;
        }

        $defaultRates = Setting::get('default_commission_rates', [
            'inhouse' => 2.5,
            'agency' => 3.0,
            'independent' => 3.0,
        ]);

        $type = $this->agent_type ?? 'inhouse';
        return (float)($defaultRates[$type] ?? 2.5);
    }
}
