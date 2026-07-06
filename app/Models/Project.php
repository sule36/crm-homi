<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'code', 'description', 'location', 'address',
        'latitude', 'longitude', 'total_units', 'sold_units',
        'booked_units', 'available_units', 'price_range_min',
        'price_range_max', 'master_plan_image', 'brochure_file',
        'logo', 'status', 'amenities', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'settings' => 'array',
            'price_range_min' => 'integer',
            'price_range_max' => 'integer',
        ];
    }

    // Relationships
    public function unitTypes()
    {
        return $this->hasMany(UnitType::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    // Computed
    public function recalculateUnits(): void
    {
        $minPrice = $this->unitTypes()->min('current_price') ?? 0;
        $maxPrice = $this->unitTypes()->max('current_price') ?? 0;

        $this->update([
            'total_units' => $this->units()->count(),
            'sold_units' => $this->units()->where('status', 'sold')->count(),
            'booked_units' => $this->units()->where('status', 'booked')->count(),
            'available_units' => $this->units()->where('status', 'available')->count(),
            'price_range_min' => $minPrice,
            'price_range_max' => $maxPrice,
        ]);
    }

    public function getOccupancyRateAttribute(): float
    {
        if ($this->total_units === 0) return 0;
        return round(($this->sold_units / $this->total_units) * 100, 1);
    }
}
