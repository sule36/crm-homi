<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    protected $fillable = [
        'user_id', 'period_month', 'period_year', 'basic_salary',
        'total_allowances', 'total_deductions', 'bonus', 'overtime',
        'net_salary', 'status', 'paid_at', 'paid_by',
        'payment_method', 'bank_name', 'reference_number', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'integer',
            'total_allowances' => 'integer',
            'total_deductions' => 'integer',
            'bonus' => 'integer',
            'overtime' => 'integer',
            'net_salary' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted()
    {
        // When payroll is marked as paid, record to General Ledger
        static::updated(function ($payroll) {
            if ($payroll->wasChanged('status') && $payroll->status === 'paid') {
                GeneralLedger::recordEntry(
                    type: 'expense',
                    category: 'salary',
                    amount: (int) $payroll->net_salary,
                    reference: $payroll,
                    projectId: null,
                    description: "Gaji {$payroll->user->name} - " . $payroll->period_label,
                    date: $payroll->paid_at ?? now(),
                    recordedBy: $payroll->paid_by,
                );
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deductions()
    {
        return $this->hasMany(PayrollDeduction::class);
    }

    public function paidByUser()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function getPeriodLabelAttribute(): string
    {
        $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return ($months[$this->period_month] ?? '') . ' ' . $this->period_year;
    }

    public function getGrossSalaryAttribute(): int
    {
        return $this->basic_salary + $this->total_allowances + $this->bonus + $this->overtime;
    }
}
