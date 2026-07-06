<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractorTermin extends Model
{
    protected $table = 'contractor_termins';

    protected $fillable = [
        'contractor_contract_id', 'rab_item_id', 'label', 'percentage',
        'amount', 'status', 'due_date', 'paid_date', 'expense_id',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'float',
            'amount' => 'integer',
            'due_date' => 'date',
            'paid_date' => 'date',
        ];
    }

    public function contract()
    {
        return $this->belongsTo(ContractorContract::class, 'contractor_contract_id');
    }

    public function rabItem()
    {
        return $this->belongsTo(RabItem::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
}
