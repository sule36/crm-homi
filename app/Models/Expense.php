<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'project_id', 'expense_category_id', 'description', 'amount',
        'expense_date', 'payment_method', 'vendor_name', 'receipt_number',
        'receipt_file', 'notes', 'recorded_by', 'approved_by', 'status',
        'bank_account_id', 'ppn_amount', 'pph_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'ppn_amount' => 'integer',
            'pph_amount' => 'integer',
            'expense_date' => 'date',
        ];
    }

    protected static function booted()
    {
        // Auto-record to General Ledger when expense is created
        static::created(function ($expense) {
            if ($expense->status === 'approved') {
                GeneralLedger::recordEntry(
                    type: 'expense',
                    category: 'operational_expense',
                    amount: $expense->amount,
                    reference: $expense,
                    projectId: $expense->project_id,
                    description: $expense->description,
                    date: $expense->expense_date,
                    recordedBy: $expense->recorded_by,
                    bankAccountId: $expense->bank_account_id,
                );
            }
        });

        // Remove GL entry when expense is deleted
        static::deleted(function ($expense) {
            GeneralLedger::removeForReference($expense);
        });
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }
}
