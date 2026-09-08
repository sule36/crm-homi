<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($reservation) {
            if (empty($reservation->reservation_number)) {
                $reservation->reservation_number = static::generateReservationNumber($reservation->project_id);
            }
            if (!$reservation->expires_at) {
                $reservation->expires_at = now()->addDays(7);
            }
        });
    }

    protected $fillable = [
        'reservation_number', 'project_id', 'unit_id', 'lead_id', 'negotiation_id', 'created_by',
        'client_name', 'client_phone', 'client_email', 'client_nik',
        'amount', 'payment_method', 'payment_proof', 'status', 'refundable_policy',
        'agent_coordinator_id', 'agent_coordinator_name', 'agent_coordinator_title',
        'refund_amount', 'refund_date', 'refund_bank_name', 'refund_account_number', 'refund_account_name',
        'refund_reason', 'refund_proof', 'refunded_by',
        'booking_id', 'expires_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'refund_amount' => 'integer',
            'refund_date' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    // --- Relationships ---

    public function project()
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function negotiation()
    {
        return $this->belongsTo(Negotiation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function agentCoordinator()
    {
        return $this->belongsTo(User::class, 'agent_coordinator_id');
    }

    public function refunder()
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    // --- Helpers ---

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast() && $this->status === 'active';
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'active' => 'Reservasi Aktif',
            'converted' => 'Dikonversi ke Booking (SPR)',
            'refunded' => 'Dibatalkan & Di-Refund 100%',
            'cancelled' => 'Dibatalkan',
            default => strtoupper($this->status),
        };
    }

    public static function generateReservationNumber($projectId = null): string
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
        $monthRoman = $romanMonths[$monthNum] ?? 'IX';

        $format = Setting::get('reservation_number_format');
        if (empty($format) || !str_contains($format, '{month_roman}')) {
            $format = '{seq}/RSV-{code}/{month_roman}/{year}';
        }

        return str_replace(
            ['{seq2}', '{seq}', '{code}', '{year}', '{month_roman}', '{month}'],
            [$nextSeq2, $nextSeq3, $projectCode, $year, $monthRoman, sprintf('%02d', $monthNum)],
            $format
        );
    }
}
