<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrokerCompany extends Model
{
    protected $fillable = ['name', 'contact_person', 'phone', 'email', 'address', 'commission_rate', 'status'];

    public function leads() { return $this->hasMany(Lead::class); }
}
