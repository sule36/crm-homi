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
            background-color: #f5f5f5;
            padding: 4px 8px;
            font-weight: bold;
            font-size: 9pt;
            border-left: 3px solid #d97706;
            margin-bottom: 8px;
            color: #171717;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 2.5px 0;
            font-size: 8.5pt;
            vertical-align: top;
        }
        .info-table td.lbl {
            width: 38%;
            color: #525252;
        }
        .info-table td.val {
            font-weight: 500;
            color: #171717;
        }

        /* SCHEDULING TABLE */
        .schedule-title {
            font-weight: bold;
            font-size: 9pt;
            margin-bottom: 6px;
            color: #171717;
        }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .schedule-table th {
            background-color: #f5f5f5;
            color: #404040;
            font-weight: bold;
            font-size: 8pt;
            padding: 5px 6px;
            border: 1px solid #e5e5e5;
            text-align: left;
        }
        .schedule-table td {
            padding: 4.5px 6px;
            font-size: 8pt;
            border: 1px solid #e5e5e5;
            color: #262626;
        }
        .schedule-table tr.total-row td {
            font-weight: bold;
            background-color: #fafafa;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* CATATAN-CATATAN (TERMS) */
        .catatan-section {
            margin-bottom: 15px;
        }
        .catatan-header {
            font-weight: bold;
            font-size: 9pt;
            margin-bottom: 6px;
            color: #171717;
        }
        .catatan-list {
            font-size: 7.5pt;
            color: #404040;
            line-height: 1.35;
        }
        .catatan-item {
            margin-bottom: 3px;
            padding-left: 14px;
            text-indent: -14px;
        }
        .catatan-num {
            display: inline-block;
            width: 14px;
            font-weight: bold;
        }

        /* SIGNATURE SECTION */
        .signature-section {
            margin-top: 15px;
        }
        .sig-date {
            text-align: right;
            font-size: 8.5pt;
            margin-bottom: 8px;
            color: #404040;
        }
        .sig-table {
            width: 100%;
            border-collapse: collapse;
        }
        .sig-table td {
            text-align: center;
            vertical-align: top;
            padding: 0 4px;
        }
        .sig-title {
            font-size: 8.5pt;
            color: #404040;
            margin-bottom: 6px;
        }
        .sig-box {
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sig-img {
            max-height: 44px;
            max-width: 110px;
            object-fit: contain;
        }
        .sig-name {
            font-weight: normal;
            font-size: 8.5pt;
            margin-top: 4px;
        }

        /* PAGE 2 SPECIAL OFFER & BENEFIT */
        .page-break {
            page-break-before: always;
        }
        .so-header-title {
            text-align: center;
            font-size: 15pt;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 25px;
            color: #171717;
        }
        .so-meta {
            width: 100%;
            margin-bottom: 25px;
            font-size: 10pt;
        }
        .so-meta td {
            padding: 3px 0;
            vertical-align: top;
        }
        .so-meta td.so-lbl {
            width: 130px;
            color: #404040;
        }
        .so-section-title {
            font-size: 10.5pt;
            font-weight: bold;
            margin-top: 18px;
            margin-bottom: 8px;
            color: #171717;
        }
        .so-list {
            list-style: none;
            padding-left: 15px;
            margin: 0;
            font-size: 9.5pt;
            line-height: 1.6;
            color: #333;
        }
        .so-list li {
            margin-bottom: 3px;
        }
        .so-footer {
            margin-top: 35px;
            font-style: italic;
            font-size: 9pt;
            color: #666;
        }
    </style>
</head>
<body>

    @php
        // Safe base64 image helper to prevent DOMPDF blank screen crashes
        $getSafeBase64 = function($relativePath) {
            if (empty($relativePath)) return null;
            $fullPath = storage_path('app/public/' . $relativePath);
            if (file_exists($fullPath) && is_file($fullPath)) {
                try {
                    $content = @file_get_contents($fullPath);
                    if ($content) {
                        $mime = mime_content_type($fullPath) ?: 'image/png';
                        return 'data:' . $mime . ';base64,' . base64_encode($content);
                    }
                } catch (\Throwable $e) {}
            }
            return null;
        };

        // Resolve Developer / Project Logo
        $logoData = null;
        $project = $booking->unit->project ?? null;
        
        if ($project && $project->logo) {
            $logoData = $getSafeBase64($project->logo);
        }
        if (!$logoData && isset($settings['company_logo'])) {
            $logoData = $getSafeBase64($settings['company_logo']);
        }
        if (!$logoData && file_exists(public_path('images/logo.png'))) {
            $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('images/logo.png')));
        }

        // Terms and Conditions
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

        // Signatures setup
        $sigs = $settings['spr_signatures'] ?? [
            'city' => 'Jakarta Selatan',
            'sig1_title' => 'Sales Manager',
            'sig1_name' => 'Dhany Nur',
            'sig1_image' => null,
            'sig2_title' => 'Direktur',
            'sig2_name' => 'Luhur Wira Pramudya',
            'sig2_image' => null,
            'sig3_title' => 'Pembeli Utama',
        ];

        $bankInfo = $settings['spr_bank_info'] ?? [
            'bank_name' => 'BCA / BSI',
            'account_number' => '542-539-2929 / 732-694-3422',
            'account_holder' => 'PT. Serangkai Roden Development',
        ];

        // Special Offer & Benefit
        $so = $settings['spr_special_offer'] ?? [
            'enabled' => true,
            'title' => 'Special Offer & Benefit ' . ($project->name ?? 'Umala Andara'),
            'bonus_furniture' => [
                'Kitchen Set',
                'Kitchen Island',
                'Dinding Feature Wall Backdrop TV (Sesuai rumah contoh)',
                'Bench',
                'Wall Cabinet TV',
            ],
            'grand_launching_package' => [
                'Free BPHTB ((khusus aset perolehan pertama)',
                'Free AJB',
                'Free Balik Nama',
                'Free Biaya Notaris',
                'Extra Cashback 50 Juta',
            ],
            'promo_valid_until' => '30 September 2024',
        ];

        // Prepare Base64 Signature Images
        $sig1ImageData = $getSafeBase64($sigs['sig1_image'] ?? null);
        $sig2ImageData = $getSafeBase64($sigs['sig2_image'] ?? null);
        $sig3ImageData = $getSafeBase64($sigs['sig3_image'] ?? null);
        $sig4ImageData = $getSafeBase64($sigs['sig4_image'] ?? null);

        // Buyer details
        $buyerNik = $booking->buyer_nik ?? $booking->lead->identity_number ?? '-';
        $buyerNpwp = $booking->buyer_npwp ?? $booking->lead->npwp ?? '-';
        $buyerAddress = $booking->buyer_address ?? $booking->lead->address ?? '-';
        $buyerJob = $booking->buyer_job ?? $booking->lead->job ?? '-';
    @endphp

    <!-- PAGE 1: SURAT PEMESANAN RUMAH (SPR) -->

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
            <td style="padding-right: 10px;">
                <div class="info-header">Informasi Pemesan Utama</div>
                <table class="info-table">
                    <tr>
                        <td class="lbl">Nama</td>
                        <td class="val">: {{ $booking->lead->name }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">No. KTP</td>
                        <td class="val">: {{ $buyerNik }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">NPWP</td>
                        <td class="val">: {{ $buyerNpwp }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">No. Telp / WA</td>
                        <td class="val">: {{ $booking->lead->phone }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Alamat KTP</td>
                        <td class="val">: {{ $buyerAddress }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Email</td>
                        <td class="val">: {{ $booking->lead->email ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Pekerjaan</td>
                        <td class="val">: {{ $buyerJob }}</td>
                    </tr>
                </table>

                @if(!empty($booking->secondary_name))
                <div class="info-header" style="margin-top: 8px;">Penanggung Jawab / Pemesan 2</div>
                <table class="info-table">
                    <tr>
                        <td class="lbl">Nama 2</td>
                        <td class="val">: {{ $booking->secondary_name }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">No. KTP 2</td>
                        <td class="val">: {{ $booking->secondary_nik ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Hubungan</td>
                        <td class="val">: {{ $booking->secondary_relationship ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">No. Telp 2</td>
                        <td class="val">: {{ $booking->secondary_phone ?? '-' }}</td>
                    </tr>
                </table>
                @endif
            </td>

            <!-- RIGHT: INFORMASI UNIT -->
            <td style="padding-left: 10px;">
                <div class="info-header">Informasi Unit Properti</div>
                <table class="info-table">
                    <tr>
                        <td class="lbl">No. Unit / Kavling</td>
                        <td class="val">: Blok {{ $booking->unit->block }} No. {{ $booking->unit->number }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Tipe Rumah</td>
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
                        <td class="lbl">Harga Jual Unit</td>
                        <td class="val">: Rp {{ number_format($booking->base_price ?: $booking->final_price, 2, '.', ',') }}</td>
                    </tr>
                    @if($booking->ppn_amount > 0 || $booking->bphtb_amount > 0 || $booking->ajb_bbn_amount > 0)
                    <tr>
                        <td class="lbl">Legalitas & Pajak</td>
                        <td class="val">: Rp {{ number_format(($booking->ppn_amount + $booking->bphtb_amount + $booking->ajb_bbn_amount + $booking->other_legal_fees), 2, '.', ',') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="lbl">Harga Jual Kesepakatan</td>
                        <td class="val" style="color: #d97706; font-weight: bold;">: Rp {{ number_format($booking->final_price, 2, '.', ',') }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Cara Pembayaran</td>
                        <td class="val">: {{ strtoupper(str_replace('_', ' ', $booking->payment_scheme)) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- JADWAL PEMBAYARAN TABLE -->
    <div class="schedule-title">Informasi Pembayaran</div>
    <table class="schedule-table">
        <thead>
            <tr>
                <th style="width: 32%;">Informasi Pembayaran</th>
                <th style="width: 20%;" class="text-right">Nilai (Rp)</th>
                <th style="width: 14%;" class="text-center">Tanggal</th>
                <th style="width: 16%;">Bank & Rekening</th>
                <th style="width: 18%;">Nama Rekening</th>
            </tr>
        </thead>
        <tbody>
            @php
                $formattedBank = ($bankInfo['bank_name'] ?? 'BCA') . ' ' . ($bankInfo['account_number'] ?? '');
                $accHolder = $bankInfo['account_holder'] ?? 'PT. Serangkai Roden Development';
            @endphp

            @if($booking->paymentSchedules && $booking->paymentSchedules->count() > 0)
                @foreach($booking->paymentSchedules as $sched)
                <tr>
                    <td>{{ $sched->label }}</td>
                    <td class="text-right">{{ number_format($sched->amount, 2, '.', ',') }}</td>
                    <td class="text-center">{{ date('d/m/Y', strtotime($sched->due_date)) }}</td>
                    <td>{{ $formattedBank }}</td>
                    <td>{{ $accHolder }}</td>
                </tr>
                @endforeach
            @else
            <tr>
                <td>Booking Fee (UTJ)</td>
                <td class="text-right">{{ number_format($booking->booking_fee, 2, '.', ',') }}</td>
                <td class="text-center">{{ date('d/m/Y', strtotime($booking->booking_date)) }}</td>
                <td>{{ $formattedBank }}</td>
                <td>{{ $accHolder }}</td>
            </tr>
            <tr>
                <td>Pelunasan {{ strtoupper($booking->payment_scheme) }}</td>
                <td class="text-right">{{ number_format($booking->final_price - $booking->booking_fee, 2, '.', ',') }}</td>
                <td class="text-center">14 Hari</td>
                <td>{{ $formattedBank }}</td>
                <td>{{ $accHolder }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td>Total Kesepakatan</td>
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

    <!-- SIGNATURE SECTION (DYNAMIC COLUMNS 2, 3, or 4) -->
    @php
        $sigSlots = [];
        // Slot 1: Sales / Staff
        $sigSlots[] = [
            'title' => $sigs['sig1_title'] ?? 'Sales Agent',
            'name' => $sigs['sig1_name'] ?? ($booking->bookedBy->name ?? 'Staff'),
            'image' => $sig1ImageData,
        ];
        // Slot 2: Management / Direktur
        if (!empty($sigs['sig2_title'])) {
            $sigSlots[] = [
                'title' => $sigs['sig2_title'] ?? 'Direktur',
                'name' => $sigs['sig2_name'] ?? 'Luhur Wira Pramudya',
                'image' => $sig2ImageData,
            ];
        }
        // Slot 3: Pemesan Utama
        $sigSlots[] = [
            'title' => $sigs['sig3_title'] ?? 'Pemesan Utama',
            'name' => $booking->lead->name,
            'image' => $sig3ImageData,
        ];
        // Slot 4: Penanggung Jawab / Pemesan 2 (if present or configured)
        if (!empty($booking->secondary_name) || !empty($sigs['sig4_title'])) {
            $sigSlots[] = [
                'title' => $sigs['sig4_title'] ?? ($booking->secondary_relationship ? 'Penanggung Jawab (' . $booking->secondary_relationship . ')' : 'Pemesan 2'),
                'name' => $booking->secondary_name ?? ($sigs['sig4_name'] ?? '-'),
                'image' => $sig4ImageData,
            ];
        }

        $colWidth = floor(100 / count($sigSlots));
    @endphp

    <div class="signature-section">
        <div class="sig-date">
            {{ $sigs['city'] ?? 'Jakarta Selatan' }}, {{ date('j F Y', strtotime($booking->booking_date)) }}
        </div>
        <table class="sig-table">
            <tr>
                @foreach($sigSlots as $slot)
                <td style="width: {{ $colWidth }}%;">
                    <div class="sig-title">{{ $slot['title'] }}</div>
                    <div class="sig-box">
                        @if(!empty($slot['image']))
                            <img src="{{ $slot['image'] }}" class="sig-img">
                        @endif
                    </div>
                    <div class="sig-name">({{ $slot['name'] }})</div>
                </td>
                @endforeach
            </tr>
        </table>
    </div>


    <!-- PAGE 2: SPECIAL OFFER & BENEFIT LAMPIRAN DOCUMENT -->
    @if(!empty($so['enabled']))
    <div class="page-break"></div>

    <div class="so-header-title">{{ $so['title'] ?? ('Special Offer & Benefit ' . ($project->name ?? 'Umala Andara')) }}</div>

    <table class="so-meta">
        <tr>
            <td class="so-lbl">Nama</td>
            <td>: {{ $booking->lead->name }}@if(!empty($booking->secondary_name)) / {{ $booking->secondary_name }} ({{ $booking->secondary_relationship ?? 'Penanggung Jawab' }})@endif</td>
        </tr>
        <tr>
            <td class="so-lbl">No Unit</td>
            <td>: Blok {{ $booking->unit->block }} No. {{ $booking->unit->number }}</td>
        </tr>
        <tr>
            <td class="so-lbl">Harga</td>
            <td>: Rp. {{ number_format($booking->final_price, 0, ',', '.') }},-</td>
        </tr>
        <tr>
            <td class="so-lbl">Cara Bayar</td>
            <td>: {{ strtoupper(str_replace('_', ' ', $booking->payment_scheme)) }} @if($booking->payment_scheme === 'kpr') (DP {{ $booking->dp_amount > 0 ? 'Ada' : '0%' }}) @endif</td>
        </tr>
    </table>

    @php
        $bonusItems = $booking->special_bonus_items ?? ($so['bonus_furniture'] ?? [
            'Kitchen Set',
            'Kitchen Island',
            'Dinding Feature Wall Backdrop TV (Sesuai rumah contoh)',
            'Bench',
            'Wall Cabinet TV',
        ]);

        $packageItems = $booking->special_package_items ?? ($so['grand_launching_package'] ?? [
            'Free BPHTB ((khusus aset perolehan pertama)',
            'Free AJB',
            'Free Balik Nama',
            'Free Biaya Notaris',
            'Extra Cashback 50 Juta',
        ]);

        $promoValidUntil = $so['promo_valid_until'] ?? '30 September 2024';
    @endphp

    <div class="so-section-title">Special Bonus Furniture:</div>
    <ul class="so-list">
        @foreach($bonusItems as $item)
            <li>- {{ $item }}</li>
        @endforeach
    </ul>

    <div class="so-section-title">Special Grand Launching Package:</div>
    <ul class="so-list">
        @foreach($packageItems as $pkg)
            <li>- {{ $pkg }}</li>
        @endforeach
    </ul>

    <div class="so-footer">
        *Promo berlaku hingga {{ $promoValidUntil }}
    </div>
    @endif

</body>
</html>
