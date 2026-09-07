<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Negotiation extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($negotiation) {
            $negotiation->token = Str::random(24);
            if (!$negotiation->expired_at) {
                $negotiation->expired_at = now()->addDays(7);
            }
        });
    }

    protected $fillable = [
        'token', 'lead_id', 'unit_id', 'project_id', 'created_by',
        // Client
        'client_name', 'client_phone', 'client_email',
        // Negotiation
        'unit_listed_price', 'offered_price', 'payment_scheme',
        'dp_amount', 'installment_months', 'special_requests', 'notes',
        // Status
        'status', 'counter_price', 'counter_notes',
        'reviewed_by', 'reviewed_at',
        'client_response', 'client_response_at',
        'booking_id', 'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_listed_price' => 'integer',
            'offered_price' => 'integer',
            'dp_amount' => 'integer',
            'counter_price' => 'integer',
            'installment_months' => 'integer',
            'reviewed_at' => 'datetime',
            'client_response_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    // --- Relationships ---

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    // --- Scopes ---

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['expired', 'rejected'])
                     ->where(function ($q) {
                         $q->whereNull('expired_at')
                           ->orWhere('expired_at', '>', now());
                     });
    }

    // --- Helpers ---

    public function isExpired(): bool
    {
        return $this->expired_at && $this->expired_at->isPast();
    }

    public function getPublicUrl(): string
    {
        return url("/nego/{$this->token}");
    }

    public function getPaymentSchemeLabel(): string
    {
        return match ($this->payment_scheme) {
            'cash_keras' => 'Cash Keras',
            'cash_bertahap' => 'Cash Bertahap',
            'kpr' => 'KPR Bank',
            default => $this->payment_scheme ?? '-',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft (Belum Diisi)',
            'pending' => 'Menunggu Review',
            'reviewed' => 'Sedang Ditinjau',
            'counter_offer' => 'Counter Offer',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'expired' => 'Kedaluwarsa',
            default => $this->status,
        };
    }
}
