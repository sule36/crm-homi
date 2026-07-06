<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerBank extends Model
{
    protected $fillable = [
        'name',
        'logo',
        'interest_rate_fixed',
        'interest_rate_floating',
        'fixed_duration',
        'is_active',
        'is_syariah',
        'syariah_margin_rate',
        'is_tiered',
        'tiered_rates',
        'show_on_homepage',
        'show_in_calculator',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_syariah' => 'boolean',
        'is_tiered' => 'boolean',
        'tiered_rates' => 'array',
        'show_on_homepage' => 'boolean',
        'show_in_calculator' => 'boolean',
    ];
}
