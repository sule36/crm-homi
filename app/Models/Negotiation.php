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
            if (empty($negotiation->negotiation_number)) {
                $negotiation->negotiation_number = static::generateNegotiationNumber($negotiation->project_id);
            }
            if (!$negotiation->expired_at) {
                $negotiation->expired_at = now()->addDays(7);
            }
        });
    }

    protected $fillable = [
        'negotiation_number', 'token', 'lead_id', 'unit_id', 'project_id', 'created_by',
        // Client
        'client_name', 'client_phone', 'client_email',
        // Negotiation
        'unit_listed_price', 'offered_price', 'payment_scheme',
        'dp_amount', 'installment_months', 'special_requests',
        'custom_layout_options', 'custom_layout_notes', 'notes', 'client_signature',
        'developer_sig_name', 'developer_sig_title',
        // Status
        'status', 'counter_price', 'counter_notes',
        'reviewed_by', 'reviewed_at',
        'client_response', 'client_response_at',
        'booking_id', 'expired_at', 'pdf_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_listed_price' => 'integer',
            'offered_price' => 'integer',
            'dp_amount' => 'integer',
            'counter_price' => 'integer',
            'installment_months' => 'integer',
            'custom_layout_options' => 'array',
            'reviewed_at' => 'datetime',
            'client_response_at' => 'datetime',
            'expired_at' => 'datetime',
            'pdf_generated_at' => 'datetime',
        ];
    }

    public function getPublicPdfUrl(): string
    {
        return url("/nego/{$this->token}/pdf");
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

    public static function generateNegotiationNumber($projectId = null): string
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

        $format = Setting::get('negotiation_number_format');
        if (empty($format) || !str_contains($format, '{month_roman}')) {
            $format = '{seq}/NG-{code}/{month_roman}/{year}';
        }

        return str_replace(
            ['{seq2}', '{seq}', '{code}', '{year}', '{month_roman}', '{month}'],
            [$nextSeq2, $nextSeq3, $projectCode, $year, $monthRoman, sprintf('%02d', $monthNum)],
            $format
        );
    }

    public function getFormattedNumber(): string
    {
        if (!empty($this->negotiation_number)) {
            return $this->negotiation_number;
        }

        $seq = sprintf('%03d', $this->id ?? 1);
        $projectCode = 'ALC';
        if ($this->project_id) {
            $project = $this->project ?? Project::find($this->project_id);
            if ($project) {
                $projectCode = !empty($project->code)
                    ? strtoupper($project->code)
                    : (strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $project->name), 0, 3)) ?: 'ALC');
            }
        }

        $createdDate = $this->created_at ?? now();
        $romanMonths = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        $monthRoman = $romanMonths[(int)$createdDate->format('n')] ?? 'IX';
        $year = $createdDate->format('Y');

        return "{$seq}/NG-{$projectCode}/{$monthRoman}/{$year}";
    }
}
