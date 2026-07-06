<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RabRealization extends Model
{
    protected $fillable = [
        'rab_item_id', 'expense_id', 'amount', 'realization_date',
        'vendor_name', 'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'realization_date' => 'date',
        ];
    }

    public function rabItem()
    {
        return $this->belongsTo(RabItem::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
