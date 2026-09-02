<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterLeadInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'master_lead_id',
        'invoice_type',
        'reward_name',
        'fee_per_unit',
        'total_amount',
        'status',
        'notes',
        'submitted_at',
        'paid_at',
        'payment_proof',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'float',
            'fee_per_unit' => 'float',
            'submitted_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function masterLead()
    {
        return $this->belongsTo(User::class, 'master_lead_id');
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class, 'master_lead_invoice_id');
    }

    public static function generateInvoiceNumber($masterLead, string $type = 'commission'): string
    {
        $typePrefix = match ($type) {
            'closing_fee' => 'INV/ML-CF',
            'reward' => 'INV/ML-RWD',
            default => 'INV/ML-KOM',
        };
        $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $masterLead->name ?? 'KANAHOMI'), 0, 8));
        $year = date('Y');
        $count = static::whereYear('created_at', $year)->where('invoice_type', $type)->count() + 1;
        $padded = str_pad($count, 4, '0', STR_PAD_LEFT);

        return "{$typePrefix}/{$code}/{$year}/{$padded}";
    }
}
