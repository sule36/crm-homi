<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitPriceHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'user_id',
        'old_price',
        'new_price',
        'promo',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'old_price' => 'integer',
            'new_price' => 'integer',
        ];
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
