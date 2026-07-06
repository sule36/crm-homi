<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id', 'name', 'code', 'building_area', 'land_area',
        'bedrooms', 'bathrooms', 'floors', 'base_price', 'current_price',
        'floor_plan_image', 'specs', 'description',
    ];

    protected function casts(): array
    {
        return [
            'specs' => 'array',
            'base_price' => 'integer',
            'current_price' => 'integer',
        ];
    }

    protected static function booted()
    {
        static::created(function ($unitType) {
            $unitType->project?->recalculateUnits();
        });

        static::updated(function ($unitType) {
            if ($unitType->wasChanged('project_id') || $unitType->wasChanged('current_price')) {
                $unitType->project?->recalculateUnits();
                if ($unitType->wasChanged('project_id') && $unitType->getOriginal('project_id')) {
                    Project::find($unitType->getOriginal('project_id'))?->recalculateUnits();
                }
            }
        });

        static::deleted(function ($unitType) {
            $unitType->project?->recalculateUnits();
        });

        static::restored(function ($unitType) {
            $unitType->project?->recalculateUnits();
        });
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function getAvailableCountAttribute(): int
    {
        return $this->units()->where('status', 'available')->count();
    }
}
