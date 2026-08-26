<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $fillable = [
        'user_id', 'broker_company_id', 'booking_id', 'amount',
        'base_commission', 'promo_bonus', 'rate_used', 'payout_recipient',
        'status', 'paid_at', 'notes', 'receipt_number'
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function brokerCompany() { return $this->belongsTo(BrokerCompany::class); }
    public function booking() { return $this->belongsTo(Booking::class); }
}
