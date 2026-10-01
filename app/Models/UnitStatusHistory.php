<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitStatusHistory extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'unit_id',
        'user_id',
        'old_status',
        'new_status',
        'notes',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
