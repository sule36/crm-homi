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

    public function getFormDetails(): array
    {
        $saved = is_array($this->form_data) ? $this->form_data : [];

        $createdDate = $this->created_at ?? now();
        $dateFormatted = $createdDate->format('d') . ' ' . $this->getIndonesianMonth($createdDate->format('n')) . ' ' . $createdDate->format('Y');

        $projectName = $this->project->name ?? 'Alonica Hills';
        $projectAddress = $this->project->address ?? 'Jl. Bhakti, RT.002/RW.007, Cilandak Tim, Ps Minggu, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta. 12560';
        $kavling = $this->unit ? ('Blok ' . ($this->unit->block ?? 'A3') . ($this->unit->number ? ' ' . $this->unit->number : '')) : 'Blok A3';
        $unitType = $this->unit->unitType->name ?? 'Badan';
        $luasTanah = $this->unit->surface_area ?? $this->unit->unitType->land_area ?? '105';
        $luasBangunan = $this->unit->building_area ?? $this->unit->unitType->building_area ?? '198';

        $priceListVal = $this->unit_listed_price ? number_format($this->unit_listed_price, 0, ',', '.') . ',-' : '3,840,921,600,-';
        $offeredPriceVal = $this->offered_price ? number_format($this->offered_price, 0, ',', '.') . ',-' : '3,550,921,600,-';
        $counterPriceVal = $this->counter_price ? number_format($this->counter_price, 0, ',', '.') . ',-' : '3,700,000,000,-';

        $defaultPengajuanNotes = [
            "Harga yang diajukan sudah termasuk PPN, AJB, BPHTB, dan biaya balik nama SHM atas nama pembeli. Ketentuan ini berlaku tanpa dipengaruhi status insentif PPN DTP pemerintah pada saat serah terima",
            "Serah terima unit paling lambat 10 (Sepuluh) bulan kalender sejak pembayaran DP. Keterlambatan dikenakan denda 1‰ (satu permil) per hari dari jumlah yang telah dibayarkan pembeli.",
            "Spesifikasi teknis bangunan (merek, tipe, dan ukuran material) dilampirkan dan menjadi bagian tidak terpisahkan dari PPJB.",
            "PBG dan sertifikat induk dalam kondisi bebas hak tanggungan ditunjukkan kepada pembeli sebelum pembayaran DP dilakukan.",
            "Free legalitas (PPN, AJB, BPHTB, SHM) dan bonus (canopy carport, smart door lock) tetap berlaku atas harga yang disetujui.",
            "Luas tanah pada form tertulis " . $luasTanah . " m². Mohon konfirmasi angka final sesuai gambar kavling dan hasil pengukuran, untuk dicantumkan dalam PPJB.",
            "Reservasi sebesar Rp 10.000.000,- dikembalikan penuh apabila pengajuan ini tidak disetujui atau persyaratan legalitas tidak terpenuhi."
        ];

        $defaultJawabanNotes = [
            "Booking Fee sebesar Rp17.000.000,- dinyatakan hangus 100% apabila setelah SPR diterbitkan, pembeli membatalkan atau tidak melanjutkan transaksi pembelian unit.",
            "Seluruh biaya all-in mengacu pada pengajuan dan kesepakatan sebelumnya, termasuk PPN, AJB, BPHTB, balik nama SHM, serta biaya legalitas dan biaya terkait lainnya yang telah disepakati. Apabila terdapat custom layout, perubahan desain, atau permintaan khusus dari pembeli, maka seluruh biaya tambahan yang timbul atas permintaan tersebut tidak menjadi tanggungan Developer.",
            "Apabila DP 1 sebesar Rp200.000.000,- telah diterima oleh Developer, namun pembeli membatalkan atau tidak melanjutkan transaksi sampai dengan proses PPJB pada bulan Desember 2026 sebagaimana telah disepakati, maka sebesar 20% dari DP 1 atau Rp40.000.000,- dinyatakan hangus. Potongan sebesar 20% tersebut diperhitungkan atas biaya yang telah timbul, termasuk biaya notaris, biaya desain, fee marketing, serta kehilangan potensi transaksi (lost opportunity) akibat penjadwalan pembayaran hingga Desember 2026. Sisa sebesar Rp160.000.000,- akan dikembalikan kepada pembeli setelah unit tersebut berhasil terjual kembali kepada pembeli berikutnya.",
            "Pembeli diperbolehkan menempati Unit " . ($this->unit ? ($this->unit->block . ($this->unit->number ?? '')) : 'A3') . " setelah proses penandatanganan PPJB antara pembeli dan Developer telah dilaksanakan, sesuai dengan ketentuan dan kesepakatan para pihak."
        ];

        $bonusStr = '(Canopy Carport, SmartdoorLock)';
        if (!empty($this->special_bonus_items) && is_array($this->special_bonus_items)) {
            $bonusStr = '(' . implode(', ', $this->special_bonus_items) . ')';
        }

        return [
            'kepada' => $saved['kepada'] ?? ("PT. Serangkai Roden Development – " . $projectName),
            'dari' => $saved['dari'] ?? ($this->client_name ?: 'Eko Sukaryanto'),
            'cc' => $saved['cc'] ?? 'KanaHomi – Agent Coordinator',
            'tanggal' => $saved['tanggal'] ?? $dateFormatted,

            'project_name' => $saved['project_name'] ?? $projectName,
            'project_address' => $saved['project_address'] ?? $projectAddress,
            'kavling' => $saved['kavling'] ?? $kavling,
            'type' => $saved['type'] ?? $unitType,
            'luas_tanah' => $saved['luas_tanah'] ?? (string)$luasTanah,
            'luas_bangunan' => $saved['luas_bangunan'] ?? (string)$luasBangunan,
            'price_list' => $saved['price_list'] ?? $priceListVal,
            'reservasi' => $saved['reservasi'] ?? '10,000,000,-',
            'reservasi_date' => $saved['reservasi_date'] ?? '8 Sept 2026',
            'diskon' => $saved['diskon'] ?? '45,000,000,- + 5,000,000,-',
            'free_legalitas' => $saved['free_legalitas'] ?? '(PPN, AJB, BPHTB, SHM)',
            'bonus' => $saved['bonus'] ?? $bonusStr,

            'pengajuan' => array_merge([
                'cara_bayar' => $this->payment_scheme === 'cash_keras' ? 'Cash Keras' : ($this->payment_scheme === 'kpr' ? 'KPR Bank' : 'Cash Bertahap 3X'),
                'price' => $offeredPriceVal,
                'reservasi' => '10,000,000,-',
                'reservasi_date' => '08 Sept 2026',
                'booking_fee' => '7,000,000,-',
                'booking_fee_total' => '(Total 17,000,000,-)',
                'booking_fee_date' => '24 Sept 2026',
                'dp1_pct' => '%',
                'dp1_amount' => $this->dp_amount ? number_format($this->dp_amount, 0, ',', '.') . ',-' : '200,000,000,-',
                'dp1_date' => '1 Okt 2026',
                'dp2_amount' => '1,666,960,800,-',
                'dp2_date' => '25 Des 2026',
                'pelunasan_amount' => '1,666,960,800,-',
                'pelunasan_date' => '25 Des 2027',
                'notes' => $defaultPengajuanNotes,
            ], $saved['pengajuan'] ?? []),

            'jawaban' => array_merge([
                'show_jawaban' => true,
                'cara_bayar' => 'Cash Bertahap 3X',
                'price' => $counterPriceVal,
                'reservasi' => '10,000,000,-',
                'reservasi_date' => '08 Sept 2026',
                'booking_fee' => '7,000,000,-',
                'booking_fee_total' => '(Total 17,000,000,-)',
                'booking_fee_date' => '24 Sept 2026',
                'dp1_pct' => '%',
                'dp1_amount' => '200,000,000,-',
                'dp1_date' => '1 Okt 2026',
                'dp2_amount' => '1,741,500,000,-',
                'dp2_date' => '25 Des 2026',
                'pelunasan_amount' => '1,741,500,000,-',
                'pelunasan_date' => '25 Des 2027',
                'notes' => $defaultJawabanNotes,
                'sig_city_date' => 'Jakarta, ' . $dateFormatted,
                'sig_pengaju_name' => $this->client_name ?: 'Eko Sukaryanto',
                'sig_mengetahui_name' => 'Maulizar',
                'sig_menyetujui_name' => 'Ch. Bramantyo P.',
            ], $saved['jawaban'] ?? []),
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
