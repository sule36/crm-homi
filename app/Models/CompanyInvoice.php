<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'invoice_number',
        'plan',
        'amount',
        'period_start',
        'period_end',
        'due_date',
        'status',
        'payment_method',
        'payment_proof',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function generateInvoiceNumber(): string
    {
        $yearMonth = date('Ym');
        $count = static::whereYear('created_at', date('Y'))->whereMonth('created_at', date('m'))->count() + 1;
        return sprintf('INV-SAAS-%s-%03d', $yearMonth, $count);
    }
}
