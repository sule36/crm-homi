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
        // Consumer Full Details & Secondary Buyer
        'buyer_nik', 'buyer_npwp', 'buyer_address', 'buyer_job',
        'secondary_name', 'secondary_nik', 'secondary_phone', 'secondary_relationship', 'secondary_address', 'secondary_email',
        'special_bonus_items', 'special_package_items',
        // Per-Booking Signature Overrides
        'sig1_title', 'sig1_name', 'sig2_title', 'sig2_name',
        'sig3_title', 'sig3_name', 'sig4_title', 'sig4_name',
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
            'special_bonus_items' => 'array',
            'special_package_items' => 'array',
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

    // Auto-generate SPR number
    public static function generateSpkNumber($projectId = null): string
    {
        return static::generateSprNumber($projectId);
    }

    public static function formatSprNumberForBooking($booking): string
    {
        $createdAt = $booking->created_at ? strtotime($booking->created_at) : time();
        $year = date('Y', $createdAt);
        $monthNum = (int)date('n', $createdAt);
        
        $romanMonths = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        $monthRoman = $romanMonths[$monthNum] ?? 'VIII';

        $seqVal = $booking->id ?? 1;
        $seq3 = sprintf('%03d', $seqVal);
        $seq2 = sprintf('%02d', $seqVal);

        $projectCode = 'ALC';
        $project = $booking->project ?? ($booking->unit->project ?? Project::first());
        if ($project) {
            if (!empty($project->code)) {
                $projectCode = strtoupper($project->code);
            } else {
                $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $project->name);
                $projectCode = strtoupper(substr($cleanName, 0, 3)) ?: 'ALC';
            }
        }

        $format = Setting::get('spr_number_format');
        if (empty($format) || !str_contains($format, '{month_roman}')) {
            $format = '{seq}/SPR-{code}/{month_roman}/{year}';
            Setting::set('spr_number_format', $format);
        }

        return str_replace(
            ['{seq2}', '{seq}', '{code}', '{year}', '{month_roman}', '{month}'],
            [$seq2, $seq3, $projectCode, $year, $monthRoman, sprintf('%02d', $monthNum)],
            $format
        );
    }

    public function getSpkNumberAttribute($value): string
    {
        $hasRomanMonth = preg_match('/\\/(I|II|III|IV|V|VI|VII|VIII|IX|X|XI|XII)\\//i', $value ?? '');
        if (empty($value) || str_contains($value, 'HOMI') || str_starts_with($value, 'SPR-2026-') || !$hasRomanMonth) {
            return static::formatSprNumberForBooking($this);
        }
        return $value;
    }

    public static function generateSprNumber($projectId = null): string
    {
        $year = date('Y');
        $countThisYear = static::whereYear('created_at', $year)->count();
        $nextSeq3 = sprintf('%03d', $countThisYear + 1);
        $nextSeq2 = sprintf('%02d', $countThisYear + 1);

        $projectCode = 'ALC';
        $project = null;
        if ($projectId) {
            $project = Project::find($projectId);
        }
        if (!$project) {
            $project = Project::first();
        }

        if ($project) {
            if (!empty($project->code)) {
                $projectCode = strtoupper($project->code);
            } else {
                $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $project->name);
                $projectCode = strtoupper(substr($cleanName, 0, 3)) ?: 'ALC';
            }
        }

        $romanMonths = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        $monthNum = (int)date('n');
        $monthRoman = $romanMonths[$monthNum] ?? 'VIII';

        $format = Setting::get('spr_number_format');
        if (empty($format) || !str_contains($format, '{month_roman}')) {
            $format = '{seq}/SPR-{code}/{month_roman}/{year}';
            Setting::set('spr_number_format', $format);
        }

        return str_replace(
            ['{seq2}', '{seq}', '{code}', '{year}', '{month_roman}', '{month}'],
            [$nextSeq2, $nextSeq3, $projectCode, $year, $monthRoman, sprintf('%02d', $monthNum)],
            $format
        );
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
