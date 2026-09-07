<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Proposal Negosiasi - {{ $negotiation->client_name }} - Unit {{ $negotiation->unit->code ?? '-' }}</title>
    @if(request()->has('html') || request()->query('view') === 'html')
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @endif
    <style>
        @page {
            margin: 0.6cm 0.8cm;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 8.5pt;
            color: #1e293b;
            line-height: 1.35;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
        }

        /* HEADER & KOP SURAT */
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .company-logo {
            max-height: 48px;
            max-width: 170px;
            object-fit: contain;
        }
        .company-title {
            font-size: 13pt;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .company-subtitle {
            font-size: 7.5pt;
            color: #475569;
            margin-top: 2px;
        }

        /* WATERMARK STAMP */
        .watermark-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
        }
        .status-approved { background-color: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .status-pending { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .status-counter { background-color: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe; }
        .status-rejected { background-color: #ffe4e6; color: #9f1239; border: 1px solid #fecdd3; }
        .status-draft { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

        /* DOCUMENT TITLE & REF */
        .doc-header {
            text-align: center;
            margin-bottom: 14px;
        }
        .doc-title {
            font-size: 11.5pt;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .doc-ref {
            font-size: 8pt;
            color: #64748b;
        }

        /* SECTION BOX */
        .section-box {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 10px;
            overflow: hidden;
        }
        .section-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 5px 8px;
            font-size: 8.5pt;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section-body {
            padding: 8px;
        }

        /* TWO COLUMN DATA TABLES */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table td {
            padding: 4px 6px;
            vertical-align: top;
            font-size: 8.5pt;
        }
        .data-label {
            color: #64748b;
            font-weight: 600;
            width: 32%;
        }
        .data-value {
            color: #0f172a;
            font-weight: 700;
        }

        /* PRICE HIGHLIGHT TABLE */
        .price-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        .price-table th {
            background-color: #f1f5f9;
            padding: 5px 8px;
            font-size: 7.5pt;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            text-align: left;
            border-bottom: 1px solid #cbd5e1;
        }
        .price-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 8.5pt;
        }

        /* CUSTOM LAYOUT CHECKLIST GRID */
        .checklist-table {
            width: 100%;
            border-collapse: collapse;
        }
        .checklist-table td {
            width: 50%;
            padding: 3px 6px;
            vertical-align: middle;
            font-size: 8pt;
        }
        .check-icon {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 3px;
            background-color: #10b981;
            color: #ffffff;
            font-weight: bold;
            font-size: 8pt;
            text-align: center;
            line-height: 12px;
            margin-right: 5px;
        }

        /* NOTES TEXT AREA */
        .notes-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 8pt;
            color: #334155;
            white-space: pre-line;
            margin-top: 4px;
        }

        /* SIGNATURE SECTION */
        .signature-table {
            width: 100%;
            margin-top: 18px;
            border-collapse: collapse;
        }
        .signature-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            font-size: 8pt;
        }
        .sig-box {
            height: 55px;
            margin: 6px 0;
            display: block;
        }
        .sig-image {
            max-height: 50px;
            max-width: 180px;
            object-fit: contain;
        }
        .sig-name {
            font-weight: 800;
            color: #0f172a;
            text-decoration: underline;
        }

        /* FOOTER */
        .footer-note {
            margin-top: 14px;
            padding-top: 6px;
            border-top: 1px dashed #cbd5e1;
            font-size: 7pt;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- KOP SURAT / HEADER -->
    <table class="header-table">
        <tr>
            <td style="width: 60%; vertical-align: middle;">
                @if(!empty($settings['company_logo']))
                    <img src="{{ public_path('storage/' . $settings['company_logo']) }}" class="company-logo" />
                @else
                    <div class="company-title">{{ $settings['company_name'] ?? 'HOMI DEVELOPER' }}</div>
                @endif
                <div class="company-subtitle">
                    {{ $settings['company_address'] ?? 'Official Real Estate & Property Developer' }}
                    @if(!empty($settings['company_phone'])) · Telp: {{ $settings['company_phone'] }} @endif
                </div>
            </td>
            <td style="width: 40%; text-align: right; vertical-align: middle;">
                @php
                    $statusClass = match($negotiation->status) {
                        'approved' => 'status-approved',
                        'pending' => 'status-pending',
                        'counter_offer' => 'status-counter',
                        'rejected' => 'status-rejected',
                        default => 'status-draft',
                    };
                    $statusText = match($negotiation->status) {
                        'approved' => '✅ DISETUJUI DEVELOPER',
                        'pending' => '⏳ MENUNGGU REVIEW',
                        'counter_offer' => '🔄 COUNTER OFFER',
                        'rejected' => '❌ DITOLAK',
                        'draft' => '📝 DRAFT PENGAJUAN',
                        default => strtoupper($negotiation->status),
                    };
                @endphp
                <span class="watermark-badge {{ $statusClass }}">{{ $statusText }}</span>
            </td>
        </tr>
    </table>

    <!-- JUDUL DOKUMEN -->
    <div class="doc-header">
        <div class="doc-title">SURAT PENGAJUAN & HASIL NEGOSIASI RESMI</div>
        <div class="doc-ref">No. Pengajuan: <strong>NEGO-{{ strtoupper($negotiation->token) }}</strong> · Tanggal: {{ optional($negotiation->created_at)->format('d/m/Y H:i') ?? date('d/m/Y') }}</div>
    </div>

    <!-- 1. DATA PEMOHON & UNIT PROPERTI -->
    <div class="section-box">
        <div class="section-header">1. Identitas Pemohon & Detail Unit Diminati</div>
        <div class="section-body">
            <table class="data-table">
                <tr>
                    <td class="data-label">Nama Pemohon</td>
                    <td class="data-value">: {{ $negotiation->client_name }}</td>
                    <td class="data-label">Nama Proyek</td>
                    <td class="data-value">: {{ $negotiation->project->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="data-label">No. WhatsApp / HP</td>
                    <td class="data-value">: {{ $negotiation->client_phone }}</td>
                    <td class="data-label">Kode Unit</td>
                    <td class="data-value">: Unit {{ $negotiation->unit->code ?? $negotiation->unit->number ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="data-label">Email</td>
                    <td class="data-value">: {{ $negotiation->client_email ?? '-' }}</td>
                    <td class="data-label">Tipe & Spesifikasi</td>
                    <td class="data-value">: Tipe {{ $negotiation->unit->unitType->name ?? '-' }} (LB: {{ $negotiation->unit->building_area ?? $negotiation->unit->unitType->building_area ?? '-' }} m² / LT: {{ $negotiation->unit->surface_area ?? $negotiation->unit->unitType->surface_area ?? '-' }} m²)</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- 2. RINCIAN PENGAJUAN HARGA & PEMBAYARAN -->
    <div class="section-box">
        <div class="section-header">2. Pengajuan Harga & Skema Pembayaran</div>
        <div class="section-body">
            <table class="price-table">
                <thead>
                    <tr>
                        <th>Harga Listing Resmi</th>
                        <th>Harga Penawaran Diajukan</th>
                        <th>Potongan Selisih (Diskon)</th>
                        <th>Skema Pembayaran</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="font-weight: bold;">Rp {{ number_format($negotiation->unit_listed_price, 0, ',', '.') }}</td>
                        <td style="font-weight: bold; color: #047857;">
                            @if($negotiation->offered_price)
                                Rp {{ number_format($negotiation->offered_price, 0, ',', '.') }}
                            @else
                                <span style="color: #94a3b8; font-style: italic;">Belum Diisi</span>
                            @endif
                        </td>
                        <td style="font-weight: bold; color: #dc2626;">
                            @if($negotiation->offered_price && $negotiation->unit_listed_price > $negotiation->offered_price)
                                @php
                                    $diff = $negotiation->unit_listed_price - $negotiation->offered_price;
                                    $pct = number_format(($diff / $negotiation->unit_listed_price) * 100, 1);
                                @endphp
                                -Rp {{ number_format($diff, 0, ',', '.') }} ({{ $pct }}%)
                            @else
                                -
                            @endif
                        </td>
                        <td style="font-weight: bold;">
                            {{ match($negotiation->payment_scheme) { 'cash_keras' => 'Cash Keras (Pelunasan 30 Hari)', 'cash_bertahap' => 'Cash Bertahap Direct Developer', 'kpr' => 'KPR Bank Partner', default => $negotiation->payment_scheme ?? '-' } }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <table class="data-table" style="margin-top: 8px;">
                <tr>
                    <td class="data-label">Rencana Nominal DP</td>
                    <td class="data-value">: {{ $negotiation->dp_amount ? 'Rp ' . number_format($negotiation->dp_amount, 0, ',', '.') : '-' }}</td>
                    <td class="data-label">Tenor Cicilan</td>
                    <td class="data-value">: {{ $negotiation->installment_months ? $negotiation->installment_months . ' Bulan' : '-' }}</td>
                </tr>
                @if($negotiation->payment_scheme === 'kpr' && $negotiation->offered_price && $negotiation->installment_months)
                    @php
                        $principal = max(0, $negotiation->offered_price - ($negotiation->dp_amount ?? 0));
                        $rate = 0.05 / 12;
                        $months = $negotiation->installment_months;
                        $emi = ($principal * $rate * pow(1 + $rate, $months)) / (pow(1 + $rate, $months) - 1);
                    @endphp
                    <tr>
                        <td class="data-label">Estimasi Angsuran KPR</td>
                        <td class="data-value" colspan="3" style="color: #1d4ed8;">
                            : ~Rp {{ number_format(round($emi), 0, ',', '.') }} / bulan *(Estimasi bunga 5% p.a. fixed)*
                        </td>
                    </tr>
                @endif
            </table>
        </div>
    </div>

    <!-- 3. PENGAJUAN CUSTOM LAYOUT & MODIFIKASI DENAH -->
    <div class="section-box">
        <div class="section-header">3. Pengajuan Custom Layout & Modifikasi Denah Bangunan</div>
        <div class="section-body">
            @if(!empty($negotiation->custom_layout_options) && is_array($negotiation->custom_layout_options) && count($negotiation->custom_layout_options) > 0)
                <table class="checklist-table">
                    @foreach(array_chunk($negotiation->custom_layout_options, 2) as $row)
                        <tr>
                            @foreach($row as $opt)
                                <td><span class="check-icon">✓</span> <strong>{{ $opt }}</strong></td>
                            @endforeach
                            @if(count($row) === 1)
                                <td></td>
                            @endif
                        </tr>
                    @endforeach
                </table>
            @else
                <div style="font-size: 8pt; color: #64748b; font-style: italic;">Tidak ada pilihan penyesuaian denah standar yang dicentang.</div>
            @endif

            @if($negotiation->custom_layout_notes)
                <div style="font-size: 8pt; font-weight: bold; color: #0f172a; margin-top: 6px;">Detail Catatan Denah Custom & Tata Letak:</div>
                <div class="notes-box">{{ $negotiation->custom_layout_notes }}</div>
            @endif
        </div>
    </div>

    <!-- 4. COUNTER OFFER DEVELOPER (IF ANY) -->
    @if($negotiation->status === 'counter_offer' || $negotiation->counter_price)
        <div class="section-box" style="border-color: #c084fc;">
            <div class="section-header" style="background-color: #faf5ff; color: #6b21a8; border-color: #e9d5ff;">
                4. Hasil Review & Penawaran Balik (Counter Offer) Developer
            </div>
            <div class="section-body">
                <table class="data-table">
                    <tr>
                        <td class="data-label" style="color: #6b21a8;">Nominal Counter Offer</td>
                        <td class="data-value" style="font-size: 10pt; color: #6b21a8;">: Rp {{ number_format($negotiation->counter_price, 0, ',', '.') }}</td>
                    </tr>
                    @if($negotiation->counter_notes)
                        <tr>
                            <td class="data-label">Catatan Reviewer</td>
                            <td class="data-value">: <em>"{{ $negotiation->counter_notes }}"</em></td>
                        </tr>
                    @endif
                    @if($negotiation->client_response)
                        <tr>
                            <td class="data-label">Tanggapan Pemohon</td>
                            <td class="data-value">:
                                <strong>
                                    {{ match($negotiation->client_response) { 'accepted' => '✅ Diterima Pemohon', 'rejected' => '❌ Ditolak Pemohon', 'revised' => '🔄 Mengajukan Revisi', default => $negotiation->client_response } }}
                                </strong>
                                @if($negotiation->client_response_at) ({{ $negotiation->client_response_at->format('d/m/Y H:i') }}) @endif
                            </td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>
    @endif

    <!-- 5. PERMINTAAN KHUSUS TAMBAHAN & CATATAN -->
    @if($negotiation->special_requests || $negotiation->notes)
        <div class="section-box">
            <div class="section-header">5. Permintaan Khusus & Catatan Lainnya</div>
            <div class="section-body">
                @if($negotiation->special_requests)
                    <div style="font-size: 8pt; font-weight: bold; color: #0f172a;">Permintaan Khusus Tambahan:</div>
                    <div class="notes-box">{{ $negotiation->special_requests }}</div>
                @endif
                @if($negotiation->notes)
                    <div style="font-size: 8pt; font-weight: bold; color: #0f172a; margin-top: 6px;">Catatan Tambahan:</div>
                    <div class="notes-box">{{ $negotiation->notes }}</div>
                @endif
            </div>
        </div>
    @endif

    <!-- LEMBAR OTENTIKASI & TANDA TANGAN -->
    <table class="signature-table">
        <tr>
            <td>
                <div>Pemohon / Calon Pembeli,</div>
                <div class="sig-box">
                    @if(!empty($negotiation->client_signature))
                        <img src="{{ $negotiation->client_signature }}" class="sig-image" />
                    @else
                        <div style="height: 45px; border-bottom: 1px dashed #cbd5e1; width: 140px; margin: 0 auto;"></div>
                    @endif
                </div>
                <div class="sig-name">{{ $negotiation->client_name }}</div>
                <div style="font-size: 7.5pt; color: #64748b;">(Tanda Tangan Digital Pemohon)</div>
            </td>
            <td>
                <div>Developer Sales Representative,</div>
                <div class="sig-box">
                    <div style="height: 45px; border-bottom: 1px dashed #cbd5e1; width: 140px; margin: 0 auto; display: flex; items-center; justify-content: center;">
                        <span style="font-size: 7pt; color: #94a3b8; font-style: italic; line-height: 45px;">[ Verified by System ]</span>
                    </div>
                </div>
                <div class="sig-name">{{ $negotiation->creator->name ?? $settings['spr_signature_name'] ?? 'Developer CRM Management' }}</div>
                <div style="font-size: 7.5pt; color: #64748b;">{{ $settings['company_name'] ?? 'Homi Developer' }}</div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Dokumen ini diterbitkan secara elektronik oleh Sistem CRM Developer pada {{ date('d F Y, H:i') }} WIB.
        Segala bentuk kesepakatan akhir negosiasi dan perubahan denah custom akan disahkan melalui penandatanganan Surat Pemesanan Rumah (SPR).
    </div>

</body>
</html>
