<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitProgress extends Model
{
    use HasFactory;

    protected $table = 'unit_progress';

    protected $fillable = [
        'unit_id', 'progress_percentage', 'description', 'notes',
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
