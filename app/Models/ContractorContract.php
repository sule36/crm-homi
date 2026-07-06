<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractorContract extends Model
{
    protected $fillable = [
        'project_id', 'contractor_name', 'contract_number',
        'description', 'total_amount', 'status',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function termins()
    {
        return $this->hasMany(ContractorTermin::class);
    }

    public function getPaidAmountAttribute(): int
    {
        return (int) $this->termins()->where('status', 'paid')->sum('amount');
    }

    public function getRemainingAmountAttribute(): int
    {
        return $this->total_amount - $this->paid_amount;
    }
}
