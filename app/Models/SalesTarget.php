<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesTarget extends Model
{
    protected $fillable = [
        'user_id', 'month', 'target_revenue', 'target_units',
        'target_leads', 'achieved_units', 'achieved_revenue',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
