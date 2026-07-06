<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeSalary extends Model
{
    protected $fillable = [
        'user_id', 'basic_salary', 'transport_allowance', 'meal_allowance',
        'position_allowance', 'other_allowance', 'effective_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'integer',
            'transport_allowance' => 'integer',
            'meal_allowance' => 'integer',
            'position_allowance' => 'integer',
            'other_allowance' => 'integer',
            'effective_date' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getTotalAllowancesAttribute(): int
    {
        return $this->transport_allowance + $this->meal_allowance
             + $this->position_allowance + $this->other_allowance;
    }

    public function getGrossSalaryAttribute(): int
    {
        return $this->basic_salary + $this->total_allowances;
    }
}
