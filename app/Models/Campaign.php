<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = [
        'project_id', 'name', 'platform', 'budget',
        'start_date', 'end_date', 'utm_source', 'utm_medium',
        'utm_campaign', 'leads_count', 'conversions_count',
        'cost_per_lead', 'status',
    ];

    public function project() { return $this->belongsTo(Project::class); }
    public function leads() { return $this->hasMany(Lead::class, 'utm_campaign', 'utm_campaign'); }
}
