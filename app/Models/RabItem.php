<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RabItem extends Model
{
    protected $fillable = [
        'project_id', 'category', 'sub_category', 'description',
        'unit', 'volume', 'unit_price', 'total_price', 'notes', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'volume' => 'float',
            'unit_price' => 'integer',
            'total_price' => 'integer',
        ];
    }

    protected static function booted()
    {
        // Auto-compute total_price
        static::saving(function ($item) {
            $item->total_price = (int) ($item->volume * $item->unit_price);
        });
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function realizations()
    {
        return $this->hasMany(RabRealization::class);
    }

    public function getRealizationTotalAttribute(): int
    {
        return (int) $this->realizations()->sum('amount');
    }

    public function getDeviationAttribute(): int
    {
        return $this->total_price - $this->realization_total;
    }

    public function getRealizationPercentAttribute(): float
    {
        if ($this->total_price <= 0) return 0;
        return round(($this->realization_total / $this->total_price) * 100, 1);
    }

    /**
     * RAB category labels for display
     */
    public static function categoryLabels(): array
    {
        return [
            'tanah' => '🏞️ Tanah & Pematangan',
            'pondasi' => '🧱 Pondasi',
            'struktur' => '🏗️ Struktur (Kolom, Balok, Plat)',
            'dinding' => '🧱 Dinding & Partisi',
            'atap' => '🏠 Atap & Rangka',
            'mep_listrik' => '⚡ MEP - Listrik',
            'mep_plumbing' => '🚿 MEP - Plumbing',
            'finishing_interior' => '🎨 Finishing Interior',
            'finishing_exterior' => '🏢 Finishing Exterior',
            'infrastruktur' => '🛤️ Infrastruktur (Jalan, Drainase)',
            'perizinan' => '📜 Perizinan & Legal',
            'overhead' => '📊 Overhead & Manajemen',
            'lain_lain' => '📦 Lain-lain',
        ];
    }
}
