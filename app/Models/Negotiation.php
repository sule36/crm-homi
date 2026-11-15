<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Negotiation extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

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
        'dp_amount', 'installment_months', 'special_requests', 'special_bonus_items',
        'custom_layout_options', 'custom_layout_notes', 'notes', 'form_data', 'client_signature',
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
            'special_bonus_items' => 'array',
            'form_data' => 'array',
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

        $projectCode = 'ALC';
        $project = null;
        if ($projectId) {
            $project = Project::withoutGlobalScopes()->find($projectId);
        }
        if (!$project) {
            $project = Project::withoutGlobalScopes()->first();
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
        if (empty($format)) {
            $format = '{seq}/NG-{code}/{month_roman}/{year}';
        }

        $seq = max(1, static::withoutGlobalScopes()->withTrashed()->whereYear('created_at', $year)->count() + 1);
        $attempts = 0;
        do {
            $nextSeq3 = sprintf('%03d', $seq);
            $nextSeq2 = sprintf('%02d', $seq);

            $candidate = str_replace(
                ['{seq2}', '{seq}', '{code}', '{year}', '{month_roman}', '{month}'],
                [$nextSeq2, $nextSeq3, $projectCode, $year, $monthRoman, sprintf('%02d', $monthNum)],
                $format
            );

            $exists = static::withoutGlobalScopes()->withTrashed()->where('negotiation_number', $candidate)->exists();
            if (!$exists) {
                return $candidate;
            }
            $seq++;
            $attempts++;
        } while ($attempts < 1000);

        return sprintf('%s-%d', $candidate, time());
    }

    public function getFormattedNumber(): string
    {
        if (!empty($this->negotiation_number)) {
            return $this->negotiation_number;
        }

        $seq = sprintf('%03d', $this->id ?? 1);
        $projectCode = 'ALC';
        if ($this->project_id) {
            $project = $this->project ?? Project::withoutGlobalScopes()->find($this->project_id);
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

    public function resolveDealPrice(): int
    {
        if (($this->status === 'approved' || $this->client_response === 'accepted' || $this->status === 'counter_offer') && (int)$this->counter_price > 0) {
            return (int)$this->counter_price;
        }
        return (int)($this->counter_price ?: $this->offered_price ?: 0);
    }

    public static function formatNominal($amount): string
    {
        if ($amount === null || $amount === '' || (int)$amount <= 0) {
            return '-';
        }
        return number_format((float)$amount, 0, ',', '.') . ',-';
    }

    public static function formatSchemeLabel(?string $scheme, ?int $installmentMonths = null): string
    {
        return match ($scheme) {
            'cash_keras' => 'Cash Keras',
            'kpr' => 'KPR Bank',
            'cash_bertahap' => $installmentMonths && $installmentMonths > 0
                ? "Cash Bertahap {$installmentMonths}X"
                : 'Cash Bertahap',
            default => $scheme ? ucwords(str_replace('_', ' ', $scheme)) : '-',
        };
    }

    public function resolveLinkedReservation(): ?Reservation
    {
        if (!$this->lead_id && !$this->unit_id && !$this->id) {
            return null;
        }

        try {
            return Reservation::withoutGlobalScopes()
                ->whereIn('status', ['active', 'converted'])
                ->where(function ($q) {
                    if ($this->id) {
                        $q->where('negotiation_id', $this->id);
                    }
                    if ($this->lead_id && $this->unit_id) {
                        $q->orWhere(function ($sq) {
                            $sq->where('lead_id', $this->lead_id)->where('unit_id', $this->unit_id);
                        });
                    }
                    if ($this->lead_id) {
                        $q->orWhere('lead_id', $this->lead_id);
                    }
                })
                ->latest()
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getFormDetails(): array
    {
        $saved = is_array($this->form_data) ? $this->form_data : [];
        $sigConfig = Setting::getSignatureConfig();
        $standardBookingFee = Setting::getStandardBookingFee();
        $companyName = Setting::get('company_name') ?: ('PT. ' . ($this->project?->name ?? 'Developer Properti'));
        $city = $sigConfig['city'] ?? 'Jakarta Selatan';
        $reservation = $this->resolveLinkedReservation();

        $createdDate = $this->created_at ?? now();
        $dateFormatted = $createdDate->format('d') . ' ' . $this->getIndonesianMonth((int)$createdDate->format('n')) . ' ' . $createdDate->format('Y');

        $projectName = $this->project?->name ?? ($this->unit?->project?->name ?? 'Proyek Perumahan');
        $projectAddress = $this->project?->address ?? ($this->unit?->project?->address ?? '-');
        $kavling = $this->unit ? $this->unit->label : '-';
        $unitCode = $this->unit ? trim(($this->unit->block ?? '') . ' ' . ($this->unit->number ?? '')) : '-';
        $unitType = $this->unit?->unitType?->name ?? '-';
        $luasTanah = $this->unit?->unitType?->land_area ?? ($this->unit?->land_area ?? '-');
        $luasBangunan = $this->unit?->unitType?->building_area ?? ($this->unit?->building_area ?? '-');

        $listedAmount = (int)($this->unit_listed_price ?: $this->unit?->final_price ?: $this->unit?->unitType?->current_price ?: 0);
        $priceListVal = $listedAmount > 0 ? self::formatNominal($listedAmount) : '-';

        $offeredAmount = (int)($this->offered_price ?? 0);
        $offeredPriceVal = $offeredAmount > 0 ? self::formatNominal($offeredAmount) : '-';

        $counterAmount = (int)($this->counter_price ?? 0);
        $jawabanPriceVal = $counterAmount > 0
            ? self::formatNominal($counterAmount)
            : ($offeredAmount > 0 ? self::formatNominal($offeredAmount) : '-');

        $reservasiAmount = (int)($reservation?->amount ?? 0);
        $reservasiFormatted = $reservasiAmount > 0 ? self::formatNominal($reservasiAmount) : '-';
        $reservasiDateFormatted = $reservation?->created_at
            ? $reservation->created_at->format('d') . ' ' . $this->getIndonesianMonth((int)$reservation->created_at->format('n')) . ' ' . $reservation->created_at->format('Y')
            : '-';

        $remainingUtj = max(0, $standardBookingFee - $reservasiAmount);
        $bookingFeeFormatted = $remainingUtj > 0 ? self::formatNominal($remainingUtj) : ($standardBookingFee > 0 ? '0,- (Lunas UTJ)' : '-');
        $bookingFeeTotalFormatted = '(Total ' . self::formatNominal($standardBookingFee) . ')';

        $dp1Amount = (int)($this->dp_amount ?? 0);
        $dp1Formatted = $dp1Amount > 0 ? self::formatNominal($dp1Amount) : '-';

        $discountVal = '-';
        if ($listedAmount > 0 && $offeredAmount > 0 && $listedAmount > $offeredAmount) {
            $discountVal = self::formatNominal($listedAmount - $offeredAmount);
        } elseif (!empty($this->unit?->discount_amount) && (int)$this->unit->discount_amount > 0) {
            $discountVal = self::formatNominal($this->unit->discount_amount);
        }

        $freePpn = (bool)Setting::get('spr_default_free_ppn', true);
        $freeLegal = (bool)Setting::get('spr_default_free_legal', true);
        $legalParts = [];
        if ($freePpn) $legalParts[] = 'PPN';
        if ($freeLegal) array_push($legalParts, 'AJB', 'BPHTB', 'SHM');
        $defaultFreeLegalitas = count($legalParts) > 0 ? '(' . implode(', ', $legalParts) . ')' : '-';

        $bonusStr = '-';
        if (!empty($this->special_bonus_items) && is_array($this->special_bonus_items)) {
            $cleanBonuses = array_values(array_filter(array_map('trim', $this->special_bonus_items)));
            if (count($cleanBonuses) > 0) {
                $bonusStr = '(' . implode(', ', $cleanBonuses) . ')';
            }
        }

        $defaultCaraBayar = self::formatSchemeLabel($this->payment_scheme, $this->installment_months);
        $pengajuanBase = $offeredAmount > 0 ? $offeredAmount : $listedAmount;
        $pengajuanPelunasan = $pengajuanBase > 0 ? max(0, $pengajuanBase - $standardBookingFee - $dp1Amount) : 0;
        $jawabanBase = $counterAmount > 0 ? $counterAmount : $pengajuanBase;
        $jawabanPelunasan = $jawabanBase > 0 ? max(0, $jawabanBase - $standardBookingFee - $dp1Amount) : 0;

        $utjNoteText = self::formatNominal($standardBookingFee);
        $reservasiNoteText = $reservasiAmount > 0 ? self::formatNominal($reservasiAmount) : 'sesuai tanda jadi';
        $dp1NoteText = $dp1Amount > 0 ? self::formatNominal($dp1Amount) : 'yang disepakati';

        $defaultPengajuanNotes = [
            "Harga yang diajukan sudah termasuk " . (count($legalParts) > 0 ? implode(', ', $legalParts) . ' atas nama pembeli' : 'ketentuan pajak dan legalitas sesuai SPR') . ".",
            "Serah terima unit dilaksanakan sesuai jadwal pada Surat Pemesanan Rumah (SPR) dan PPJB yang disepakati.",
            "Spesifikasi teknis bangunan (merek, tipe, dan ukuran material) dilampirkan dan menjadi bagian tidak terpisahkan dari PPJB.",
            "PBG/IMB dan sertifikat induk ditunjukkan kepada pembeli sebelum pembayaran DP dilakukan.",
            "Fasilitas legalitas (" . (count($legalParts) > 0 ? implode(', ', $legalParts) : 'sesuai kesepakatan') . ") dan bonus " . ($bonusStr !== '-' ? $bonusStr : 'promo unit') . " berlaku atas harga yang disetujui.",
            "Luas tanah pada form tertulis " . $luasTanah . " m². Konfirmasi angka final mengacu pada gambar kavling dan hasil pengukuran BPN untuk dicantumkan dalam PPJB.",
            "Tanda jadi/reservasi sebesar Rp " . $reservasiNoteText . " dikembalikan 100% apabila pengajuan ini tidak disetujui oleh Developer."
        ];

        $defaultJawabanNotes = [
            "Booking Fee (UTJ) sebesar Rp " . $utjNoteText . " dinyatakan hangus apabila setelah SPR diterbitkan pembeli membatalkan transaksi secara sepihak.",
            "Seluruh komponen biaya mengacu pada kesepakatan akhir di SPR. Apabila terdapat permintaan custom layout atau perubahan desain di luar standar, biaya tambahan menjadi tanggungan Pembeli.",
            "Pembayaran Uang Muka (DP 1) sebesar Rp " . $dp1NoteText . " dan termin pelunasan mengikuti jadwal pembayaran resmi pada Surat Pemesanan Rumah (SPR).",
            "Pembeli diperbolehkan menempati Unit " . $unitCode . " setelah proses penandatanganan PPJB antara Pembeli dan Developer dilaksanakan sesuai ketentuan."
        ];

        $sigMengetahuiDefault = $sigConfig['sig1_name'] ?: ($this->creator?->name ?: 'Sales Coordinator');
        $sigMenyetujuiDefault = $this->developer_sig_name ?: ($sigConfig['sig2_name'] ?: 'Direktur');
        $clientDisplayName = $this->client_name ?: ($this->lead?->name ?? 'Calon Pembeli');

        $savedPengajuan = is_array($saved['pengajuan'] ?? null) ? $saved['pengajuan'] : [];
        $savedJawaban = is_array($saved['jawaban'] ?? null) ? $saved['jawaban'] : [];

        return [
            'kepada' => !empty($saved['kepada']) ? $saved['kepada'] : ($companyName . ' – ' . $projectName),
            'dari' => !empty($saved['dari']) ? $saved['dari'] : $clientDisplayName,
            'cc' => !empty($saved['cc']) ? $saved['cc'] : ($sigConfig['sig1_title'] ?? 'Agent Coordinator'),
            'tanggal' => !empty($saved['tanggal']) ? $saved['tanggal'] : $dateFormatted,

            'project_name' => !empty($saved['project_name']) ? $saved['project_name'] : $projectName,
            'project_address' => !empty($saved['project_address']) ? $saved['project_address'] : $projectAddress,
            'kavling' => !empty($saved['kavling']) ? $saved['kavling'] : $kavling,
            'type' => !empty($saved['type']) ? $saved['type'] : $unitType,
            'luas_tanah' => isset($saved['luas_tanah']) && $saved['luas_tanah'] !== '' ? (string)$saved['luas_tanah'] : (string)$luasTanah,
            'luas_bangunan' => isset($saved['luas_bangunan']) && $saved['luas_bangunan'] !== '' ? (string)$saved['luas_bangunan'] : (string)$luasBangunan,
            'price_list' => !empty($saved['price_list']) ? $saved['price_list'] : $priceListVal,
            'reservasi' => isset($saved['reservasi']) && $saved['reservasi'] !== '' ? $saved['reservasi'] : $reservasiFormatted,
            'reservasi_date' => isset($saved['reservasi_date']) && $saved['reservasi_date'] !== '' ? $saved['reservasi_date'] : $reservasiDateFormatted,
            'diskon' => isset($saved['diskon']) && $saved['diskon'] !== '' ? $saved['diskon'] : $discountVal,
            'free_legalitas' => !empty($saved['free_legalitas']) ? $saved['free_legalitas'] : $defaultFreeLegalitas,
            'bonus' => !empty($saved['bonus']) ? $saved['bonus'] : $bonusStr,

            'pengajuan' => array_merge([
                'cara_bayar' => $defaultCaraBayar,
                'price' => $offeredPriceVal,
                'reservasi' => $reservasiFormatted,
                'reservasi_date' => $reservasiDateFormatted,
                'booking_fee' => $bookingFeeFormatted,
                'booking_fee_total' => $bookingFeeTotalFormatted,
                'booking_fee_date' => $dateFormatted,
                'dp1_pct' => '%',
                'dp1_amount' => $dp1Formatted,
                'dp1_date' => '-',
                'dp2_amount' => '-',
                'dp2_date' => '-',
                'pelunasan_amount' => $pengajuanPelunasan > 0 ? self::formatNominal($pengajuanPelunasan) : '-',
                'pelunasan_date' => '-',
                'notes' => $defaultPengajuanNotes,
            ], array_filter($savedPengajuan, fn ($v) => $v !== null && $v !== '')),

            'jawaban' => array_merge([
                'show_jawaban' => true,
                'cara_bayar' => $defaultCaraBayar,
                'price' => $jawabanPriceVal,
                'reservasi' => $reservasiFormatted,
                'reservasi_date' => $reservasiDateFormatted,
                'booking_fee' => $bookingFeeFormatted,
                'booking_fee_total' => $bookingFeeTotalFormatted,
                'booking_fee_date' => $dateFormatted,
                'dp1_pct' => '%',
                'dp1_amount' => $dp1Formatted,
                'dp1_date' => '-',
                'dp2_amount' => '-',
                'dp2_date' => '-',
                'pelunasan_amount' => $jawabanPelunasan > 0 ? self::formatNominal($jawabanPelunasan) : '-',
                'pelunasan_date' => '-',
                'notes' => $defaultJawabanNotes,
                'sig_city_date' => $city . ', ' . $dateFormatted,
                'sig_pengaju_name' => $clientDisplayName,
                'sig_mengetahui_name' => $sigMengetahuiDefault,
                'sig_mengetahui_title' => $sigConfig['sig1_title'] ?? 'Mengetahui',
                'sig_mengetahui_image' => $sigConfig['sig1_image'] ?? null,
                'sig_menyetujui_name' => $sigMenyetujuiDefault,
                'sig_menyetujui_title' => $this->developer_sig_title ?: ($sigConfig['sig2_title'] ?? 'Direktur'),
                'sig_menyetujui_image' => $sigConfig['sig2_image'] ?? null,
            ], array_filter($savedJawaban, fn ($v) => $v !== null && $v !== '')),
        ];
    }

    private function getIndonesianMonth(int $monthNum): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        return $months[$monthNum] ?? 'September';
    }
}
