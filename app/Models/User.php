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

#[Fillable(['name', 'email', 'password', 'phone', 'avatar', 'company_id', 'project_id', 'broker_company_id', 'master_lead_id', 'agent_type', 'status', 'is_accepting_leads', 'lead_capacity', 'settings', 'last_login_at', 'commission_rate', 'custom_bonus', 'bank_name', 'bank_account_number', 'bank_account_name'])]
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
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function brokerCompany()
    {
        return $this->belongsTo(BrokerCompany::class);
    }

    public function masterLead()
    {
        return $this->belongsTo(User::class, 'master_lead_id');
    }

    public function subAgents()
    {
        return $this->hasMany(User::class, 'master_lead_id');
    }

    public function brokerCompanies()
    {
        return $this->hasMany(BrokerCompany::class, 'master_lead_id');
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function activities()
    {
        return $this->hasMany(LeadActivity::class);
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class, 'user_id');
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

    protected $appends = ['effective_commission_rate', 'effective_bank_account'];

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

    /**
     * Get effective payout bank account details for this agent.
     * Agency Sub-Agents (agency_agent / attached to a BrokerCompany) payout MUST go to BrokerCompany bank account.
     * Freelance Independent & In-House agents payout goes to agent's personal bank account.
     */
    public function getEffectiveBankAccountAttribute(): array
    {
        if ($this->agent_type === 'master_lead' || $this->hasRole('master_lead')) {
            return [
                'recipient_type' => 'master_lead_joint',
                'recipient_name' => 'KANAHOMI (Kana Project x Homi ID)',
                'bank_name' => 'BCA (Joint Operating)',
                'bank_account_number' => '4500959555 / 012001004640307',
                'bank_account_name' => 'PT. KANA ATLAS NUSANTARA / HOMI ID',
                'is_office' => true,
                'is_joint' => true,
                'joint_accounts' => [
                    [
                        'label' => 'Kana Project',
                        'bank_name' => 'BCA',
                        'account_number' => '4500959555',
                        'account_name' => 'PT. KANA ATLAS NUSANTARA',
                    ],
                    [
                        'label' => 'Homi ID',
                        'bank_name' => 'BCA',
                        'account_number' => '012001004640307',
                        'account_name' => 'HOMI ID / SULAIMAN',
                    ],
                ],
            ];
        }

        if (($this->agent_type === 'agency_agent' || $this->broker_company_id) && $this->brokerCompany) {
            return [
                'recipient_type' => 'office',
                'recipient_name' => $this->brokerCompany->name,
                'bank_name' => $this->brokerCompany->bank_name ?: 'Belum set',
                'bank_account_number' => $this->brokerCompany->bank_account_number ?: '-',
                'bank_account_name' => $this->brokerCompany->bank_account_name ?: $this->brokerCompany->name,
                'is_office' => true,
            ];
        }

        return [
            'recipient_type' => 'agent',
            'recipient_name' => $this->name,
            'bank_name' => $this->bank_name ?: 'Belum set',
            'bank_account_number' => $this->bank_account_number ?: '-',
            'bank_account_name' => $this->bank_account_name ?: $this->name,
            'is_office' => false,
        ];
    }
}
