<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PayrollDeduction extends Model
{
    use BelongsToTenant;

    protected $fillable = ['company_id', 'payroll_id', 'type', 'description', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}
