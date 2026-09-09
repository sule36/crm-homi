<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kwitansi Reservasi - {{ $reservation->reservation_number }} - {{ $reservation->client_name }}</title>
    <style>
        @page {
            margin: 0.8cm 1cm;
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
            border-collapse: collapse;
            table-layout: fixed;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .company-logo {
            max-height: 46px;
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

        /* REFUNDABLE BADGE STAMP */
        .badge-stamp {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        /* DOCUMENT TITLE & REF */
        .doc-header {
            text-align: center;
            margin-bottom: 14px;
        }
        .doc-title {
            font-size: 12pt;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .doc-ref {
            font-size: 8.5pt;
            color: #475569;
        }
        .doc-ref strong {
            color: #0f172a;
            font-weight: 800;
        }

        /* SECTION BOX */
        .section-box {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            margin-bottom: 10px;
            overflow: hidden;
        }
        .section-header {
            background-color: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
            padding: 5px 10px;
            font-size: 8.5pt;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .section-body {
            padding: 8px 10px;
        }

        /* TWO COLUMN DATA TABLES */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .data-table td {
            padding: 4px 5px;
            vertical-align: top;
            font-size: 8.5pt;
            word-wrap: break-word;
        }
        .data-label {
            color: #475569;
            font-weight: 600;
        }
        .data-value {
            color: #0f172a;
            font-weight: 700;
        }

        /* AMOUNT HIGHLIGHT BOX */
        .amount-box {
            background-color: #ecfdf5;
            border: 1.5px dashed #10b981;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 12px;
            text-align: center;
        }
        .amount-title {
            font-size: 8pt;
            font-weight: 700;
            color: #047857;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .amount-value {
            font-size: 16pt;
            font-weight: 900;
            color: #065f46;
            margin-top: 2px;
        }
        .amount-note {
            font-size: 7.5pt;
            color: #047857;
            margin-top: 3px;
            font-style: italic;
        }

        /* POLICY NOTICE BOX */
        .policy-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 12px;
            font-size: 8pt;
            color: #334155;
            line-height: 1.35;
        }
        .policy-title {
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 3px;
        }

        /* SIGNATURE SECTION */
        .signature-table {
            width: 100%;
            margin-top: 16px;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .signature-table td {
            text-align: center;
            vertical-align: top;
            font-size: 8pt;
            word-wrap: break-word;
        }
        .sig-box {
            height: 50px;
            margin: 6px 0;
            display: block;
        }
        .sig-image {
            max-height: 48px;
            max-width: 160px;
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

    @php
        $companyName = $reservation->company_name ?: ($settings['company_name'] ?? 'HOMI DEVELOPER');
        $companyAddress = $settings['company_address'] ?? 'Official Real Estate & Property Developer';
        $companyPhone = $settings['company_phone'] ?? null;

        $coordName = $reservation->agent_coordinator_name ?: ($reservation->agentCoordinator?->name ?? 'Agent Coordinator');
        $coordTitle = $reservation->agent_coordinator_title ?: 'Master Lead / Agent Coordinator';
        $city = $settings['spr_signatures']['city'] ?? 'Jakarta';

        $getSafeBase64 = function($path) {
            if (empty($path)) return null;
            $fullPath = str_starts_with($path, '/') ? $path : public_path('storage/' . $path);
            if (file_exists($fullPath) && is_file($fullPath)) {
                try {
                    $content = @file_get_contents($fullPath);
                    if ($content) {
                        $mime = @mime_content_type($fullPath) ?: 'image/png';
                        return 'data:' . $mime . ';base64,' . base64_encode($content);
                    }
                } catch (\Throwable $e) {}
            }
            return null;
        };

        $logoData = !empty($settings['company_logo']) ? $getSafeBase64($settings['company_logo']) : null;
        if (!$logoData && file_exists(public_path('images/logo.png'))) {
            $logoData = 'data:image/png;base64,' . base64_encode(@file_get_contents(public_path('images/logo.png')));
        }
    @endphp

    <!-- KOP SURAT / HEADER -->
    <table class="header-table">
        <colgroup>
            <col style="width: 60%;">
            <col style="width: 40%;">
        </colgroup>
        <tr>
            <td>
                @if($logoData)
                    <img src="{{ $logoData }}" class="company-logo" />
                @else
                    <div class="company-title">{{ strtoupper($companyName) }}</div>
                @endif
                <div class="company-subtitle">
                    {{ $companyAddress }}
                    @if(!empty($companyPhone)) · Telp: {{ $companyPhone }} @endif
                </div>
            </td>
            <td style="text-align: right;">
                <span class="badge-stamp">100% REFUNDABLE</span>
            </td>
        </tr>
    </table>

    <!-- JUDUL DOKUMEN -->
    <div class="doc-header">
        <div class="doc-title">KWITANSI TANDA TERIMA RESERVASI UNIT</div>
        <div class="doc-ref">No. Reservasi: <strong>{{ $reservation->reservation_number }}</strong> · Tanggal: {{ optional($reservation->created_at)->format('d/m/Y H:i') ?? date('d/m/Y') }}</div>
    </div>

    <!-- AMOUNT HIGHLIGHT -->
    <div class="amount-box">
        <div class="amount-title">Telah Diterima Biaya Reservasi Unit (Hold Booking)</div>
        <div class="amount-value">Rp {{ number_format($reservation->amount, 0, ',', '.') }}</div>
        <div class="amount-note">Metode Pembayaran: {{ strtoupper($reservation->payment_method) }} · Status: {{ $reservation->getStatusLabel() }}</div>
    </div>

    <!-- 1. RINCIAN CUSTOMER & UNIT RESERVASI -->
    <div class="section-box">
        <div class="section-header">1. Identitas Pemohon & Detail Unit Reservasi</div>
        <div class="section-body">
            <table class="data-table">
                <colgroup>
                    <col style="width: 20%;">
                    <col style="width: 30%;">
                    <col style="width: 20%;">
                    <col style="width: 30%;">
                </colgroup>
                <tr>
                    <td class="data-label">Nama Pemohon</td>
                    <td class="data-value">: {{ $reservation->client_name }}</td>
                    <td class="data-label">Nama Proyek</td>
                    <td class="data-value">: {{ $reservation->project->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="data-label">No. WhatsApp / HP</td>
                    <td class="data-value">: {{ $reservation->client_phone }}</td>
                    <td class="data-label">Kode / No. Unit</td>
                    <td class="data-value">: Unit {{ $reservation->unit->code ?? $reservation->unit->number ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="data-label">Email</td>
                    <td class="data-value">: {{ $reservation->client_email ?? '-' }}</td>
                    <td class="data-label">Tipe Properti</td>
                    <td class="data-value">: Tipe {{ $reservation->unit->unitType->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="data-label">NIK Pemohon</td>
                    <td class="data-value">: {{ $reservation->client_nik ?? '-' }}</td>
                    <td class="data-label">Spesifikasi Unit</td>
                    <td class="data-value">: LB {{ $reservation->unit->building_area ?? $reservation->unit->unitType->building_area ?? '-' }} m² / LT {{ $reservation->unit->surface_area ?? $reservation->unit->unitType->surface_area ?? '-' }} m²</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- 2. KETENTUAN PENYESUAIAN KREDIT UTJ / BOOKING -->
    <div class="section-box">
        <div class="section-header">2. Ketentuan Kredit Pemotongan Uang Tanda Jadi (UTJ)</div>
        <div class="section-body" style="font-size: 8pt; color: #334155; line-height: 1.4;">
            <p style="margin: 0 0 4px 0;">• Pembayaran reservasi ini sebesar <strong>Rp {{ number_format($reservation->amount, 0, ',', '.') }}</strong> akan <strong>memotong total Booking Fee (UTJ)</strong> secara otomatis saat pengajuan disetujui dan dikonversi menjadi Surat Pemesanan Rumah (SPR).</p>
            <p style="margin: 0;">• Contoh Kalkulasi: Apabila Booking Fee (UTJ) standar sebesar Rp 17.000.000, maka sisa pembayaran UTJ saat naik booking adalah <strong>Rp 17.000.000 - Rp {{ number_format($reservation->amount, 0, ',', '.') }} = Sisa UTJ yang harus dibayar saat booking.</strong></p>
        </div>
    </div>

    <!-- 3. GARANSI KLAUSA 100% REFUNDABLE -->
    <div class="policy-box">
        <div class="policy-title">GARANSI KLAUSA 100% REFUNDABLE (PENGEMBALIAN DANA UTUH)</div>
        <div>
            Apabila pengajuan penawaran harga/skema pembayaran tidak disetujui oleh Developer atau Calon Pembeli memutuskan untuk membatalkan pengajuan sebelum penandatanganan Surat Pemesanan Rumah (SPR), dana reservasi ini <strong>DIJAMIN DIKEMBALIKAN 100% UTUH (TANPA POTONGAN BIAYA APAPUN)</strong>.
        </div>
    </div>

    @if($reservation->notes)
        <div style="font-size: 7.5pt; color: #64748b; margin-bottom: 10px; font-style: italic;">
            *Catatan Reservasi: {{ $reservation->notes }}
        </div>
    @endif

    <!-- LEMBAR OTENTIKASI & TANDA TANGAN AGENT KOORDINATOR -->
    <table class="signature-table">
        <colgroup>
            <col style="width: 50%;">
            <col style="width: 50%;">
        </colgroup>
        <tr>
            <td>
                <div>Pemohon / Calon Pembeli,</div>
                <div class="sig-box">
                    <div style="height: 38px; border-bottom: 1px dashed #cbd5e1; width: 140px; margin: 0 auto;"></div>
                </div>
                <div class="sig-name">{{ $reservation->client_name }}</div>
                <div style="font-size: 7pt; color: #64748b;">(Tanda Tangan Pemohon)</div>
            </td>
            <td>
                <div style="font-weight: 600;">{{ $city }}, {{ optional($reservation->created_at)->format('d F Y') }}</div>
                <div style="font-weight: 600;">{{ $coordTitle }},</div>
                <div class="sig-box">
                    <div style="height: 38px; border-bottom: 1px dashed #cbd5e1; width: 140px; margin: 0 auto; text-align: center;">
                        <span style="font-size: 7pt; color: #94a3b8; font-style: italic; line-height: 38px;">[ Verified Agent System ]</span>
                    </div>
                </div>
                <div class="sig-name">{{ $coordName }}</div>
                <div style="font-size: 7.5pt; color: #475569; font-weight: 600; margin-top: 2px;">{{ $companyName }}</div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Dokumen Kwitansi Reservasi ini diterbitkan secara resmi oleh Sistem CRM Developer pada {{ date('d F Y, H:i') }} WIB.
        Segala bentuk pengembalian dana 100% akan ditransfer ke rekening bank resmi atas nama Pemohon.
    </div>

</body>
</html>
