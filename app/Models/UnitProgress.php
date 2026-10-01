<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitProgress extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'unit_progress';

    protected $fillable = [
        'company_id', 'unit_id', 'progress_percentage', 'description', 'notes',
        'recorded_date', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'progress_percentage' => 'integer',
            'recorded_date' => 'date',
        ];
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
