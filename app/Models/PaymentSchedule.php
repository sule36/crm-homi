<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PaymentSchedule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'booking_id', 'installment_number', 'label', 'amount',
        'due_date', 'paid_date', 'status', 'payment_proof',
        'verified_by', 'verified_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_date' => 'date',
            'verified_at' => 'datetime',
            'amount' => 'integer',
        ];
    }

    public function booking() { return $this->belongsTo(Booking::class); }
    public function verifiedBy() { return $this->belongsTo(User::class, 'verified_by'); }
    public function transactions() { return $this->hasMany(Transaction::class); }
}
