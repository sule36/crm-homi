<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $fillable = [
        'name', 'bank_name', 'account_number', 'account_holder',
        'initial_balance', 'current_balance', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'integer',
            'current_balance' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function generalLedgerEntries()
    {
        return $this->hasMany(GeneralLedger::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }

    /**
     * Update current balance based on all income and expenses linked to this bank account
     */
    public function syncBalance(): int
    {
        $incoming = (int) $this->generalLedgerEntries()->sum('credit');
        $outgoing = (int) $this->generalLedgerEntries()->sum('debit');

        $newBalance = $this->initial_balance + $incoming - $outgoing;
        $this->update(['current_balance' => $newBalance]);

        return $newBalance;
    }
}
