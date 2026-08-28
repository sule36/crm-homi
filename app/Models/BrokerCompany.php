<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrokerCompany extends Model
{
    protected $fillable = [
        'master_lead_id', 'name', 'code', 'contact_person', 'phone', 'email', 'address',
        'commission_rate', 'status', 'bank_name', 'bank_account_number',
        'bank_account_name', 'notes'
    ];

    public function masterLead() { return $this->belongsTo(User::class, 'master_lead_id'); }
    public function leads() { return $this->hasMany(Lead::class); }
    public function agents() { return $this->hasMany(User::class, 'broker_company_id'); }
    public function commissions() { return $this->hasMany(Commission::class, 'broker_company_id'); }
}
