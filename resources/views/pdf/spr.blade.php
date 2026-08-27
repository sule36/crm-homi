<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SPR - {{ $booking->spk_number }}</title>
    <style>
        @page {
            margin: 0.8cm 1.2cm;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9.5pt;
            color: #262626;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }

        /* HEADER LOGO & TITLE */
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .logo {
            max-height: 48px;
            max-width: 180px;
            object-fit: contain;
            margin-bottom: 4px;
        }
        .document-title {
            font-size: 11pt;
            font-weight: bold;
            color: #171717;
            margin-top: 2px;
        }
        .document-number {
            font-size: 9pt;
            color: #525252;
            margin-top: 1px;
        }

        /* TWO COLUMN INFO GRID */
        .info-grid {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .info-grid td {
            vertical-align: top;
            width: 50%;
        }
        .info-header {
            background-color: #e5e5e5;
            font-weight: bold;
            padding: 3px 6px;
            font-size: 9pt;
            color: #171717;
            margin-bottom: 6px;
            display: block;
        }
        .info-table {
            width: 98%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 2.5px 0;
            font-size: 9pt;
            border-bottom: 1px solid #e5e5e5;
        }
        .info-table td.lbl {
            color: #404040;
            width: 32%;
        }
        .info-table td.val {
            color: #171717;
            font-weight: 500;
        }

        /* PAYMENT BREAKDOWN TABLE */
        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            font-size: 9pt;
        }
        .payment-table th {
            background-color: #d4d4d4;
            color: #171717;
            padding: 5px 8px;
            text-align: left;
            font-weight: bold;
            border-bottom: 1px solid #a3a3a3;
        }
        .payment-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e5e5e5;
        }
        .payment-table tr.total-row td {
            background-color: #f5f5f5;
            font-weight: bold;
            border-top: 1.5px solid #a3a3a3;
            border-bottom: 1.5px solid #a3a3a3;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* TERMS & CONDITIONS (CATATAN-CATATAN) */
        .catatan-section {
            margin-bottom: 20px;
        }
        .catatan-header {
            font-weight: bold;
            font-size: 9.5pt;
            margin-bottom: 6px;
            color: #171717;
        }
        .catatan-list {
            margin: 0;
            padding-left: 0;
            list-style: none;
        }
        .catatan-item {
            position: relative;
            padding-left: 22px;
            margin-bottom: 4px;
            font-size: 8.5pt;
            color: #333333;
            text-align: justify;
        }
        .catatan-num {
            position: absolute;
            left: 0;
            top: 0;
            font-weight: normal;
        }

        /* SIGNATURE SECTION */
        .signature-section {
            margin-top: 25px;
            page-break-inside: avoid;
        }
        .sig-date {
            font-size: 9pt;
            margin-bottom: 15px;
            color: #171717;
        }
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
        }
        .sig-table td {
            width: 33.33%;
            vertical-align: top;
        }
        .sig-title {
            font-weight: bold;
            font-size: 9pt;
            margin-bottom: 5px;
        }
        .sig-box {
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sig-img {
            max-height: 55px;
            max-width: 140px;
            object-fit: contain;
        }
        .sig-name {
            font-weight: normal;
            font-size: 9pt;
            margin-top: 4px;
        }
    </style>
</head>
<body>

    @php
        // Resolve Developer / Project Logo
        $logoData = null;
        $project = $booking->unit->project ?? null;
        
        if ($project && $project->logo && file_exists(storage_path('app/public/' . $project->logo))) {
            $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents(storage_path('app/public/' . $project->logo)));
        } elseif (isset($settings['company_logo']) && file_exists(storage_path('app/public/' . $settings['company_logo']))) {
            $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents(storage_path('app/public/' . $settings['company_logo'])));
        } elseif (file_exists(public_path('images/logo.png'))) {
            $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('images/logo.png')));
        }

        // Settings Data
        $terms = $settings['spr_terms_conditions'] ?? [
            "Pembeli menyatakan telah mengerti dan menyetujui serta akan tunduk kepada persyaratan dan ketentuan serta kebijakan yang ditetapkan oleh Pengembang dalam SPR",
            "Dalam hal pembelian rumah melalui KPR, jumlah DP dan persyaratan KPR lainnya tunduk pada ketentuan Bank pemberi KPR",
            "Dalam hal terjadi penolakan dari pihak Bank atau KPR tidak disetujui, maka Uang Tanda Jadi akan dikembalikan 100%",
            "Dalam hal KPR yang telah disetujui oleh Bank, maka Akad Kredit wajib dilaksanakan selambat-lambatnya 1 bulan sejak diterimanya down payment oleh Pengembang",
            "Dalam hal terjadi pembatalan sepihak oleh pembeli dalam masa pembangunan unit sesuai pilihan pembeli dalam SPR ini, maka seluruh pembayaran dari Pembeli akan hangus 100%",
            "Pembeli diperkenankan untuk memilih cara pembayaran selain KPR dengan syarat dan ketentuan dari Pengembang",
            "Pembayaran segala bentuk cicilan kepada Pengembang yang melebihi waktu yang telah ditentukan dalam SPR ini, akan dikenakan denda sebesar 1% per hari dengan denda maksimal 5% dari jumlah kewajiban yang terlambat",
            "Pembeli tidak diperkenankan untuk mengalihkan pembelian tanah dan bangunan, pengalihan pembelian akan dikenakan denda sebesar 2.5% dari harga jual final",
            "Nilai Uang Tanda Jadi ditetapkan sebesar Rp. 15.000.000,- (lima belas juta rupiah)",
            "Jangka waktu perjanjian ini berakhir sesuai tanggal akhir pelunasan pembayaran oleh Pembeli, kecuali untuk KPR sesuai pelunasan dari Bank setelah Serah Terima unit kepada Pembeli",
            "SPR ini akan batal dengan sendirinya dalam hal terjadinya kondisi yang dijelaskan pada pasal 3 dan 5, Pembatalan SPR dalam bentuk tertulis antara Pengembang dan Pembeli dibuat 3 rangkap dimana 1 rangkapnya milik Pemilik Tanah",
            "Penandatanganan SPR dilakukan setelah seluruh pasal didalamnya disepakati oleh masing-masing pihak"
        ];

        $sigs = $settings['spr_signatures'] ?? [
            'city' => 'Jakarta Selatan',
            'sig1_title' => 'Sales Manager',
            'sig1_name' => 'Dhany Nur',
            'sig1_image' => null,
            'sig2_title' => 'Direktur',
            'sig2_name' => 'Luhur Wira Pramudya',
            'sig2_image' => null,
            'sig3_title' => 'Pembeli',
        ];

        $bankInfo = $settings['spr_bank_info'] ?? [
            'bank_name' => 'BCA / BSI',
            'account_number' => '542-539-2929 / 732-694-3422',
            'account_holder' => 'PT. Serangkai Roden Development',
        ];

        // Prepare Base64 Signature Images if available
        $sig1ImageData = null;
        if (!empty($sigs['sig1_image']) && file_exists(storage_path('app/public/' . $sigs['sig1_image']))) {
            $sig1ImageData = 'data:image/png;base64,' . base64_encode(file_get_contents(storage_path('app/public/' . $sigs['sig1_image'])));
        }

        $sig2ImageData = null;
        if (!empty($sigs['sig2_image']) && file_exists(storage_path('app/public/' . $sigs['sig2_image']))) {
            $sig2ImageData = 'data:image/png;base64,' . base64_encode(file_get_contents(storage_path('app/public/' . $sigs['sig2_image'])));
        }
    @endphp

    <!-- HEADER LOGO & DOCUMENT TITLE -->
    <div class="header">
        @if($logoData)
            <img src="{{ $logoData }}" class="logo"><br>
        @else
            <div style="font-size: 16pt; font-weight: bold; color: #171717;">{{ $project->name ?? 'UMALA ANDARA' }}</div>
        @endif
        <div class="document-title">Surat Pemesanan Rumah (SPR)</div>
        <div class="document-number">{{ $booking->spk_number }}</div>
    </div>

    <!-- INFORMASI PEMBELI & INFORMASI UNIT (2-COLUMN GRID) -->
    <table class="info-grid">
        <tr>
            <!-- LEFT: INFORMASI PEMBELI -->
            <td style="padding-right: 12px;">
                <div class="info-header">Informasi Pembeli</div>
                <table class="info-table">
                    <tr>
                        <td class="lbl">Nama</td>
                        <td class="val">: {{ $booking->lead->name }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">No. KTP</td>
                        <td class="val">: {{ $booking->lead->identity_number ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">No. Telp</td>
                        <td class="val">: {{ $booking->lead->phone }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">No. Telp Rumah</td>
                        <td class="val">: -</td>
                    </tr>
                    <tr>
                        <td class="lbl">Alamat Rumah</td>
                        <td class="val">: {{ $booking->lead->address ?? 'Jakarta Selatan' }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Email</td>
                        <td class="val">: {{ $booking->lead->email ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Pekerjaan</td>
                        <td class="val">: {{ $booking->lead->occupation ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Alamat Kantor</td>
                        <td class="val">: -</td>
                    </tr>
                </table>
            </td>

            <!-- RIGHT: INFORMASI UNIT -->
            <td style="padding-left: 12px;">
                <div class="info-header">Informasi Unit</div>
                <table class="info-table">
                    <tr>
                        <td class="lbl">No. Unit</td>
                        <td class="val">: Blok {{ $booking->unit->block }} No. {{ $booking->unit->number }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Type</td>
                        <td class="val">: {{ $booking->unit->unitType->name }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Luas Tanah</td>
                        <td class="val">: {{ number_format($booking->unit->unitType->land_area ?? 0, 2) }} m²</td>
                    </tr>
                    <tr>
                        <td class="lbl">Luas Bangunan</td>
                        <td class="val">: {{ number_format($booking->unit->unitType->building_area ?? 0, 2) }} m²</td>
                    </tr>
                    <tr>
                        <td class="lbl">Harga Jual Final</td>
                        <td class="val">: Rp {{ number_format($booking->final_price, 2, '.', ',') }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Cara Pembayaran</td>
                        <td class="val">: {{ strtoupper(str_replace('_', ' ', $booking->payment_scheme)) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- INFORMASI PEMBAYARAN TABLE (5 COLUMNS) -->
    <table class="payment-table">
        <thead>
            <tr>
                <th>Informasi Pembayaran</th>
                <th class="text-right">Nilai</th>
                <th class="text-center">Tanggal</th>
                <th>Bank & No. Rek Tujuan</th>
                <th>Nama Rekening</th>
            </tr>
        </thead>
        <tbody>
            @php
                $dpAmount = $booking->dp_amount > 0 ? $booking->dp_amount : 0;
                $remainingInstallment = $booking->final_price - $booking->booking_fee - $dpAmount;
                $tenorMonths = $booking->installment_months > 0 ? $booking->installment_months : 12;
                $perMonthAmount = round($remainingInstallment / $tenorMonths);
                $formattedBank = ($bankInfo['bank_name'] ?? 'BCA/BSI') . ' ' . ($bankInfo['account_number'] ?? '542-539-2929');
                $accHolder = $bankInfo['account_holder'] ?? 'PT. Serangkai Roden Development';
            @endphp
            <tr>
                <td>Uang Tanda Jadi (UTJ)</td>
                <td class="text-right">{{ number_format($booking->booking_fee, 2, '.', ',') }}</td>
                <td class="text-center">{{ date('n/j/Y', strtotime($booking->booking_date)) }}</td>
                <td>{{ $formattedBank }}</td>
                <td>{{ $accHolder }}</td>
            </tr>
            @if($dpAmount > 0)
            <tr>
                <td>Down Payment (DP)</td>
                <td class="text-right">{{ number_format($dpAmount, 2, '.', ',') }}</td>
                <td class="text-center">Sesuai Jadwal</td>
                <td>{{ $formattedBank }}</td>
                <td>{{ $accHolder }}</td>
            </tr>
            @endif
            @if(strtolower($booking->payment_scheme) === 'kpr')
            <tr>
                <td>Pencairan KPR Bank</td>
                <td class="text-right">{{ number_format($booking->final_price - $booking->booking_fee - $dpAmount, 2, '.', ',') }}</td>
                <td class="text-center">Akad Kredit</td>
                <td>{{ $formattedBank }}</td>
                <td>{{ $accHolder }}</td>
            </tr>
            @elseif(strtolower($booking->payment_scheme) === 'cash_installment' || strtolower($booking->payment_scheme) === 'cash_bertahap')
            <tr>
                <td>Cicilan Cash Bertahap ({{ $tenorMonths }} Bulan @ Rp {{ number_format($perMonthAmount, 0, ',', '.') }})</td>
                <td class="text-right">{{ number_format($remainingInstallment, 2, '.', ',') }}</td>
                <td class="text-center">Bulanan (1-{{ $tenorMonths }})</td>
                <td>{{ $formattedBank }}</td>
                <td>{{ $accHolder }}</td>
            </tr>
            @else
            <tr>
                <td>Pelunasan Cash Keras</td>
                <td class="text-right">{{ number_format($booking->final_price - $booking->booking_fee, 2, '.', ',') }}</td>
                <td class="text-center">14 Hari</td>
                <td>{{ $formattedBank }}</td>
                <td>{{ $accHolder }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td>Total</td>
                <td class="text-right">{{ number_format($booking->final_price, 2, '.', ',') }}</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <!-- CATATAN-CATATAN (TERMS & CONDITIONS DYNAMIC LIST) -->
    <div class="catatan-section">
        <div class="catatan-header">Catatan-catatan</div>
        <div class="catatan-list">
            @foreach($terms as $idx => $item)
            <div class="catatan-item">
                <span class="catatan-num">{{ $idx + 1 }}</span>
                <span>{{ $item }}</span>
            </div>
            @endforeach
        </div>
    </div>

    <!-- SIGNATURE SECTION -->
    <div class="signature-section">
        <div class="sig-date">
            {{ $sigs['city'] ?? 'Jakarta Selatan' }}, {{ date('j F Y', strtotime($booking->booking_date)) }}
        </div>
        <table class="sig-table">
            <tr>
                <td>
                    <div class="sig-title">{{ $sigs['sig1_title'] ?? 'Sales Manager' }}</div>
                    <div class="sig-box">
                        @if($sig1ImageData)
                            <img src="{{ $sig1ImageData }}" class="sig-img">
                        @endif
                    </div>
                    <div class="sig-name">({{ $sigs['sig1_name'] ?? 'Dhany Nur' }})</div>
                </td>
                <td>
                    <div class="sig-title">{{ $sigs['sig2_title'] ?? 'Direktur' }}</div>
                    <div class="sig-box">
                        @if($sig2ImageData)
                            <img src="{{ $sig2ImageData }}" class="sig-img">
                        @endif
                    </div>
                    <div class="sig-name">({{ $sigs['sig2_name'] ?? 'Luhur Wira Pramudya' }})</div>
                </td>
                <td>
                    <div class="sig-title">{{ $sigs['sig3_title'] ?? 'Pembeli' }}</div>
                    <div class="sig-box"></div>
                    <div class="sig-name">({{ $booking->lead->name }})</div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
