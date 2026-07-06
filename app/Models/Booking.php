<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($booking) {
            $booking->tracking_token = \Illuminate\Support\Str::random(16);
        });
    }

    protected $fillable = [
        'spk_number', 'lead_id', 'unit_id', 'project_id',
        'booked_by', 'approved_by', 'booking_fee', 'unit_price',
        'discount_amount', 'discount_reason', 'final_price',
        'booking_date', 'payment_scheme', 'bank_name',
        'installment_months', 'dp_amount', 'dp_installment_months',
        'status', 'cancelled_reason', 'spk_file', 'notes',
        // Tax & Legal
        'base_price', 'ppn_amount', 'bphtb_amount', 'ajb_bbn_amount', 'other_legal_fees',
        // Commission
        'commission_amount', 'commission_status', 'commission_paid_at',
        // KPR Tracking
        'kpr_status', 'kpr_bank_name', 'kpr_plafon_amount', 'kpr_sp3k_date', 'kpr_akad_date', 'kpr_notes',
        // Customer Portal
        'tracking_token',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'booking_fee' => 'float',
            'unit_price' => 'float',
            'base_price' => 'float',
            'ppn_amount' => 'float',
            'bphtb_amount' => 'float',
            'ajb_bbn_amount' => 'float',
            'other_legal_fees' => 'float',
            'discount_amount' => 'float',
            'final_price' => 'float',
            'dp_amount' => 'float',
            'commission_amount' => 'float',
            'kpr_plafon_amount' => 'float',
            'kpr_sp3k_date' => 'date',
            'kpr_akad_date' => 'date',
        ];
    }

    public function lead() { return $this->belongsTo(Lead::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function project() { return $this->belongsTo(Project::class); }
    public function bookedBy() { return $this->belongsTo(User::class, 'booked_by'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
    public function paymentSchedules() { return $this->hasMany(PaymentSchedule::class); }
    public function transactions() { return $this->hasMany(Transaction::class); }
    public function documents() { return $this->hasMany(BookingDocument::class); }

    // Auto-generate SPK number
    public static function generateSpkNumber(): string
    {
        $year = date('Y');
        $last = static::whereYear('created_at', $year)->max('id') ?? 0;
        return sprintf('SPK-%s-%04d', $year, $last + 1);
    }

    public function getTotalPaidAttribute(): int
    {
        return $this->transactions()->sum('amount');
    }

    public function getRemainingBalanceAttribute(): int
    {
        return $this->final_price - $this->total_paid;
    }
}
