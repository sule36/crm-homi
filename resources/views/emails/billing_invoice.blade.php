<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Tagihan Pembayaran</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
            color: #334155;
        }
        .wrapper {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .header {
            background: #0f172a;
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #38bdf8;
        }
        .header p {
            margin: 5px 0 0 0;
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 15px;
        }
        .alert-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
        }
        .badge-h3 { background-color: #fef3c7; color: #b45309; }
        .badge-h0 { background-color: #fee2e2; color: #b91c1c; }
        .badge-h7 { background-color: #ffe4e6; color: #9f1239; }
        .badge-manual { background-color: #e0f2fe; color: #0369a1; }
        
        .unit-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 25px;
        }
        .unit-box table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .unit-box td {
            padding: 4px 0;
        }
        .unit-box .label {
            color: #64748b;
            width: 35%;
        }
        .unit-box .val {
            font-weight: 700;
            color: #0f172a;
        }
        .invoice-card {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            margin-bottom: 25px;
        }
        .invoice-card .tag-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #94a3b8;
            margin-bottom: 6px;
        }
        .invoice-card .amount {
            font-size: 26px;
            font-weight: 900;
            color: #38bdf8;
            margin-bottom: 6px;
        }
        .invoice-card .due-date {
            font-size: 12px;
            color: #e2e8f0;
        }
        .bank-box {
            background: #fff7ed;
            border: 1px solid #ffedd5;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 25px;
        }
        .bank-box h4 {
            margin: 0 0 10px 0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #c2410c;
        }
        .bank-box p {
            margin: 3px 0;
            font-size: 12px;
            color: #431407;
        }
        .btn-cta {
            display: block;
            width: 100%;
            max-width: 280px;
            margin: 0 auto 20px auto;
            padding: 16px 24px;
            background-color: #2563eb;
            color: #ffffff;
            text-decoration: none;
            font-weight: 800;
            font-size: 13px;
            text-align: center;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .footer {
            background-color: #f8fafc;
            padding: 20px 30px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ $companyName }}</h1>
            <p>Official Customer Billing Invoice</p>
        </div>

        <div class="content">
            @php
                $leadName = $schedule->booking?->lead?->name ?? 'Bapak/Ibu Konsumen';
                $unit = $schedule->booking?->unit;
                $unitCode = ($unit?->block ?? '') . ($unit?->number ?? '');
                $projectName = $unit?->project?->name ?? 'Proyek Homi';
                $unitType = $unit?->unitType?->name ?? '-';
                $trackingLink = url("/track/" . ($schedule->booking?->tracking_token ?? ''));
                $dueDate = \Carbon\Carbon::parse($schedule->due_date)->translatedFormat('d F Y');
            @endphp

            <div class="greeting">Halo Bapak/Ibu {{ $leadName }},</div>

            @if($reminderType === 'h_3')
                <div class="alert-badge badge-h3">🔔 Pengingat Jatuh Tempo (H-3)</div>
                <p style="font-size: 13px; leading-relaxed: 1.6;">
                    Mengingatkan kembali bahwa tagihan angsuran pembayaran unit properti Anda akan jatuh tempo dalam **3 hari ke depan**.
                </p>
            @elseif($reminderType === 'h_0')
                <div class="alert-badge badge-h0">📢 Batas Waktu Pembayaran Hari Ini</div>
                <p style="font-size: 13px; leading-relaxed: 1.6;">
                    Hari ini adalah batas tanggal jatuh tempo pembayaran tagihan angsuran unit properti Anda.
                </p>
            @elseif($reminderType === 'h_plus_7')
                <div class="alert-badge badge-h7">⚠️ Peringatan Keterlambatan Tagihan</div>
                <p style="font-size: 13px; leading-relaxed: 1.6;">
                    Tagihan angsuran unit properti Anda telah melewati batas waktu pembayaran sejak {{ $dueDate }}. Mohon segera melakukan konfirmasi pelunasan.
                </p>
            @else
                <div class="alert-badge badge-manual">📑 Invoice Tagihan Angsuran</div>
                <p style="font-size: 13px; leading-relaxed: 1.6;">
                    Berikut adalah rincian tagihan angsuran pembayaran resmi untuk unit properti Anda di <strong>{{ $projectName }}</strong>.
                </p>
            @endif

            <!-- UNIT DETAIL BOX -->
            <div class="unit-box">
                <table>
                    <tr>
                        <td class="label">Proyek</td>
                        <td class="val">: {{ $projectName }}</td>
                    </tr>
                    <tr>
                        <td class="label">Unit / Tipe</td>
                        <td class="val">: Blok {{ $unitCode }} ({{ $unitType }})</td>
                    </tr>
                    <tr>
                        <td class="label">Nomor SPR</td>
                        <td class="val">: {{ $schedule->booking?->spk_number }}</td>
                    </tr>
                </table>
            </div>

            <!-- INVOICE CARD -->
            <div class="invoice-card">
                <div class="tag-label">{{ $schedule->label }} (#{{ $schedule->installment_number }})</div>
                <div class="amount">Rp {{ number_format($schedule->amount, 0, ',', '.') }}</div>
                <div class="due-date">Batas Jatuh Tempo: <strong>{{ $dueDate }}</strong></div>
            </div>

            <!-- BANK REKENING TUJUAN -->
            <div class="bank-box">
                <h4>🏦 Rekening Tujuan Pembayaran Developer</h4>
                <p><strong>Bank:</strong> {{ $bankInfo['bank_name'] ?? 'BCA / BSI' }}</p>
                <p><strong>No. Rekening:</strong> {{ $bankInfo['account_number'] ?? '542-539-2929' }}</p>
                <p><strong>Atas Nama (A/N):</strong> {{ $bankInfo['account_holder'] ?? 'PT. Serangkai Roden Development' }}</p>
            </div>

            <!-- CTA BUTTON -->
            <a href="{{ $trackingLink }}" target="_blank" class="btn-cta">
                KIRIM BUKTI BAYAR ONLINE
            </a>

            <p style="font-size: 11px; color: #94a3b8; text-align: center; margin-top: 15px;">
                Jika Anda telah melakukan pembayaran, abaikan email ini atau unggah bukti transfer melalui tombol di atas.
            </p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ $companyName }}. All rights reserved.</p>
            <p>Pesan ini dikirim secara otomatis oleh Sistem CRM Homi Developer.</p>
        </div>
    </div>
</body>
</html>
