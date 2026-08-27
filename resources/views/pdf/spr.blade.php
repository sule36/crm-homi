<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SPR - {{ $booking->spk_number }}</title>
    <style>
        @page { margin: 1cm; }
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #1e293b; line-height: 1.5; margin: 0; padding: 0; }
        .header { margin-bottom: 30px; border-bottom: 2px solid #0f172a; padding-bottom: 45px; position: relative; }
        .logo { max-width: 140px; max-height: 55px; object-fit: contain; }
        .company-info { position: absolute; right: 0; top: -5px; text-align: right; width: 320px; font-size: 8px; color: #64748b; }
        .company-name { font-size: 14px; font-weight: 900; color: #0f172a; margin-bottom: 4px; display: block; }
        
        .title-box { text-align: center; margin: 20px 0; }
        .document-title { font-size: 16px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: #0f172a; }
        .document-number { font-size: 12px; font-weight: bold; color: #d97706; margin-top: 4px; }

        .section { margin-bottom: 20px; }
        .section-title { font-size: 10px; font-weight: 900; background: #f8fafc; color: #475569; padding: 6px 12px; margin-bottom: 12px; border-left: 4px solid #f59e0b; text-transform: uppercase; letter-spacing: 1px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.data-table td { padding: 5px 0; vertical-align: top; }
        .label { width: 140px; color: #64748b; font-weight: bold; }
        .value { color: #0f172a; font-weight: bold; }

        table.schedule-table { width: 100%; margin-top: 10px; }
        table.schedule-table th { background: #0f172a; color: white; padding: 8px; font-size: 9px; text-align: left; text-transform: uppercase; }
        table.schedule-table td { padding: 8px; border-bottom: 1px solid #e2e8f0; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .summary-box { background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 10px; }
        .summary-row { display: block; margin-bottom: 5px; }
        .summary-label { display: inline-block; width: 150px; font-weight: bold; color: #475569; }
        .summary-value { font-size: 13px; font-weight: 900; color: #0f172a; }

        .footer { margin-top: 40px; page-break-inside: avoid; }
        .signature-container { width: 100%; margin-top: 30px; }
        .signature-box { width: 45%; float: left; text-align: center; }
        .signature-space { height: 70px; }
        .signature-line { border-top: 1px solid #94a3b8; width: 180px; margin: 0 auto; padding-top: 5px; font-weight: bold; font-size: 11px; }
        .clear { clear: both; }

        .terms { font-size: 9px; color: #64748b; margin-top: 30px; background: #fff; padding: 15px; border: 1px dashed #cbd5e1; border-radius: 8px; }
        .terms strong { color: #0f172a; }
        .terms ol { padding-left: 15px; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        @php
            $logoData = null;
            $project = $booking->unit->project ?? null;
            
            if ($project && $project->logo && file_exists(storage_path('app/public/' . $project->logo))) {
                $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents(storage_path('app/public/' . $project->logo)));
            } elseif (isset($settings['company_logo']) && file_exists(storage_path('app/public/' . $settings['company_logo']))) {
                $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents(storage_path('app/public/' . $settings['company_logo'])));
            } elseif (file_exists(public_path('images/logo.png'))) {
                $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('images/logo.png')));
            }
        @endphp

        @if($logoData)
            <img src="{{ $logoData }}" class="logo">
        @else
            <span class="company-name" style="font-size: 18px; color: #d97706;">{{ $booking->unit->project->name ?? 'ALONICA HILLS' }}</span>
        @endif

        <div class="company-info">
            <span class="company-name">{{ $booking->unit->project->name ?? ($settings['company_name'] ?? 'PT. SERANGKAI RODEN DEVELOPMENT') }}</span>
            {!! nl2br(e($booking->unit->project->address ?? ($settings['company_address'] ?? 'Cilandak Timur, Jakarta Selatan'))) !!}<br>
            Developer: PT. SERANGKAI RODEN DEVELOPMENT
        </div>
    </div>

    <div class="title-box">
        <div class="document-title">Surat Pemesanan Rumah (SPR)</div>
        <div class="document-number">No. SPR: {{ $booking->spk_number }}</div>
    </div>

    <div class="section">
        <div class="section-title">I. Informasi Pembeli & Unit Properti</div>
        <table class="data-table">
            <tr>
                <td class="label">Nama Pemesan / Pembeli</td>
                <td class="value">: {{ $booking->lead->name }}</td>
                <td class="label">Nama Proyek</td>
                <td class="value">: {{ $booking->unit->project->name }}</td>
            </tr>
            <tr>
                <td class="label">No. Telepon / WhatsApp</td>
                <td class="value">: {{ $booking->lead->phone }}</td>
                <td class="label">Blok / Nomor Kavling</td>
                <td class="value">: Blok {{ $booking->unit->block }} No. {{ $booking->unit->number }}</td>
            </tr>
            <tr>
                <td class="label">Skema Pembayaran</td>
                <td class="value">: {{ strtoupper(str_replace('_', ' ', $booking->payment_scheme)) }}</td>
                <td class="label">Tipe Rumah / Bangunan</td>
                <td class="value">: {{ $booking->unit->unitType->name }} (LB {{ $booking->unit->unitType->building_area }} / LT {{ $booking->unit->unitType->land_area }})</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">II. Rincian Jadwal & Skema Pelunasan</div>
        <table class="schedule-table">
            <thead>
                <tr>
                    <th>Deskripsi Pembayaran</th>
                    <th>Jatuh Tempo</th>
                    <th class="text-right">Jumlah (IDR)</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($booking->paymentSchedules as $item)
                <tr>
                    <td>{{ $item->label }}</td>
                    <td>{{ date('d M Y', strtotime($item->due_date)) }}</td>
                    <td class="text-right">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                    <td class="text-center">{{ strtoupper($item->status) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center">Booking Fee (UTJ): Rp {{ number_format($booking->booking_fee, 0, ',', '.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="summary-box">
            <div class="summary-row">
                <span class="summary-label">Harga Kesepakatan Unit:</span>
                <span class="summary-value">Rp {{ number_format($booking->final_price, 0, ',', '.') }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Booking Fee (UTJ Dibayar):</span>
                <span class="summary-value" style="color: #10b981;">Rp {{ number_format($booking->booking_fee, 0, ',', '.') }}</span>
            </div>
            <div class="summary-row" style="border-top: 1px solid #cbd5e1; margin-top: 8px; padding-top: 8px;">
                <span class="summary-label">Sisa Pembayaran / KPR:</span>
                <span class="summary-value" style="color: #d97706;">Rp {{ number_format($booking->final_price - $booking->booking_fee, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    <div class="terms">
        <strong>Syarat & Ketentuan Pemesanan Rumah (SPR):</strong>
        <div style="margin-top: 8px;">
            1. Pembayaran Down Payment (DP) atau pelunasan dilakukan paling lambat 14 hari dari pembayaran Booking Fee (UTJ).<br>
            2. Seluruh Pembayaran yang diakui adalah yang mempunyai BUKTI KWITANSI / TRANSFER resmi dari developer: PT. SERANGKAI RODEN DEVELOPMENT (BCA: 542-539-2929 ; BSI: 732-694-3422).<br>
            3. Pengajuan KPR didukung penuh oleh Developer & Bank Mitra Terdaftar.<br>
            {!! nl2br(e($settings['spk_terms'] ?? '')) !!}
        </div>
    </div>

    <div class="footer">
        <div class="signature-container">
            <div class="signature-box">
                <p>Konsumen / Pemesan,</p>
                <div class="signature-space"></div>
                <div class="signature-line">{{ $booking->lead->name }}</div>
            </div>
            <div style="width: 10%; float: left;">&nbsp;</div>
            <div class="signature-box">
                <p>Jakarta, {{ date('d F Y') }}<br>PT. Serangkai Roden Development,</p>
                <div class="signature-space"></div>
                <div class="signature-line">{{ $booking->bookedBy->name ?? 'Sales Executive' }}</div>
            </div>
            <div class="clear"></div>
        </div>
    </div>
</body>
</html>
