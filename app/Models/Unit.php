<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id', 'unit_type_id', 'block', 'number', 'floor',
        'status', 'facing_direction', 'premium_charge', 'final_price',
        'held_by', 'held_until', 'notes',
        'certificate_status', 'certificate_number', 'imb_number', 'pbb_number', 'legal_notes',
    ];

    protected function casts(): array
    {
        return [
            'final_price' => 'integer',
            'premium_charge' => 'integer',
            'held_until' => 'datetime',
        ];
    }

    protected static function booted()
    {
        static::created(function ($unit) {
            $unit->project?->recalculateUnits();
        });

        static::updated(function ($unit) {
            if ($unit->wasChanged('project_id') || $unit->wasChanged('status')) {
                $unit->project?->recalculateUnits();
                if ($unit->wasChanged('project_id') && $unit->getOriginal('project_id')) {
                    Project::find($unit->getOriginal('project_id'))?->recalculateUnits();
                }
            }
        });

        static::deleted(function ($unit) {
            $unit->project?->recalculateUnits();
        });

        static::restored(function ($unit) {
            $unit->project?->recalculateUnits();
        });
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function unitType()
    {
        return $this->belongsTo(UnitType::class);
    }

    public function heldByUser()
    {
        return $this->belongsTo(User::class, 'held_by');
    }

    public function booking()
    {
        return $this->hasOne(Booking::class)->latestOfMany();
    }

    public function progressHistory()
    {
        return $this->hasMany(UnitProgress::class)->orderBy('recorded_date', 'desc')->orderBy('created_at', 'desc');
    }

    public function latestProgress()
    {
        return $this->hasOne(UnitProgress::class)->latestOfMany();
    }

    // Label: "Blok A No. 01"
    public function getLabelAttribute(): string
    {
        $parts = [];
        if ($this->block) $parts[] = "Blok {$this->block}";
        if ($this->floor) $parts[] = "Lt. {$this->floor}";
        $parts[] = "No. {$this->number}";
        return implode(' ', $parts);
    }

    public function getDisplayPriceAttribute(): int
    {
        return $this->final_price ?: ($this->unitType?->current_price ?? 0) + $this->premium_charge;
    }
}
