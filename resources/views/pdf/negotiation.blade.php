<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Form Request Customer - {{ $negotiation->client_name }} - {{ $negotiation->unit->code ?? 'Unit' }}</title>
    <style>
        @page {
            margin: 10mm 15mm 10mm 15mm;
            size: A4 portrait;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9pt;
            color: #000000;
            line-height: 1.3;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
        }

        .header-logo-container {
            margin-bottom: 6px;
        }
        .header-logo {
            height: 52px;
            object-fit: contain;
        }

        .title-box {
            background-color: #b8cce4;
            border: 1.5px solid #5b7999;
            padding: 3px 6px;
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #000000;
            margin-bottom: 10px;
        }

        .divider-line {
            border-bottom: 1.5px solid #000000;
            margin: 8px 0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        .data-table td {
            padding: 2.2px 0;
            vertical-align: top;
        }

        .bold-title {
            font-weight: bold;
            font-size: 9pt;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        ol.note-list {
            margin: 0;
            padding-left: 18px;
            font-size: 8pt;
            line-height: 1.3;
        }
        ol.note-list li {
            margin-bottom: 3.5px;
            text-align: justify;
        }

        .sig-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            text-align: center;
            font-size: 9pt;
        }
        .sig-table td {
            vertical-align: top;
            width: 33.33%;
        }
        .sig-box {
            height: 75px;
            line-height: 75px;
            margin: 4px 0;
        }
        .sig-img {
            max-height: 75px;
            max-width: 130px;
            vertical-align: middle;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    @php
        $form = $negotiation->getFormDetails();
        $logoPath = public_path('images/alonica_logo.png');
        if (!empty($negotiation->project->logo) && file_exists(public_path('storage/' . $negotiation->project->logo))) {
            $logoPath = public_path('storage/' . $negotiation->project->logo);
        }
        $logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;

        $sigMaulizarPath = public_path('images/sig_maulizar.png');
        $sigMaulizarBase64 = file_exists($sigMaulizarPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($sigMaulizarPath)) : null;

        $sigBramantyoPath = public_path('images/sig_bramantyo.png');
        $sigBramantyoBase64 = file_exists($sigBramantyoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($sigBramantyoPath)) : null;
    @endphp

    <!-- ========================================== -->
    <!-- HALAMAN 1: FORM REQUEST CUSTOMER -->
    <!-- ========================================== -->
    <div class="header-logo-container">
        @if($logoBase64)
            <img src="{{ $logoBase64 }}" class="header-logo" alt="Logo" />
        @endif
    </div>

    <div class="title-box">
        FORM REQUEST CUSTOMER
    </div>

    <!-- METADATA SURAT -->
    <table class="data-table" style="margin-bottom: 4px;">
        <tr>
            <td style="width: 80px;">Kepada</td>
            <td style="width: 15px;">:</td>
            <td><strong>{{ $form['kepada'] }}</strong></td>
        </tr>
        <tr>
            <td>Dari</td>
            <td>:</td>
            <td><strong>{{ $form['dari'] }}</strong></td>
        </tr>
        <tr>
            <td>CC</td>
            <td>:</td>
            <td><strong>{{ $form['cc'] }}</strong></td>
        </tr>
        <tr>
            <td>Tanggal</td>
            <td>:</td>
            <td><strong>{{ $form['tanggal'] }}</strong></td>
        </tr>
    </table>

    <div class="divider-line"></div>

    <!-- DETAIL PROYEK & UNIT -->
    <table class="data-table">
        <tr>
            <td style="width: 110px;">Project</td>
            <td style="width: 15px;">:</td>
            <td colspan="2"><strong>{{ $form['project_name'] }}</strong></td>
        </tr>
        <tr>
            <td style="vertical-align: top;">Alamat</td>
            <td style="vertical-align: top;">:</td>
            <td colspan="2" style="line-height: 1.25;">{{ $form['project_address'] }}</td>
        </tr>
        <tr>
            <td>Kavling</td>
            <td>:</td>
            <td colspan="2"><strong>{{ $form['kavling'] }}</strong></td>
        </tr>
        <tr>
            <td>Type</td>
            <td>:</td>
            <td colspan="2">{{ $form['type'] }}</td>
        </tr>
        <tr>
            <td>Luas Tanah</td>
            <td>:</td>
            <td colspan="2">{{ $form['luas_tanah'] }}</td>
        </tr>
        <tr>
            <td>Luas Bangunan</td>
            <td>:</td>
            <td colspan="2">{{ $form['luas_bangunan'] }}</td>
        </tr>
        <tr>
            <td>Price list</td>
            <td>:</td>
            <td colspan="2"><strong>{{ $form['price_list'] }}</strong></td>
        </tr>
        <tr>
            <td>Reservasi</td>
            <td>:</td>
            <td style="width: 200px;">{{ $form['reservasi'] }}</td>
            <td>Tanggal : {{ $form['reservasi_date'] }}</td>
        </tr>
        <tr>
            <td>Diskon</td>
            <td>:</td>
            <td colspan="2">{{ $form['diskon'] }}</td>
        </tr>
        <tr>
            <td>Free Legalitas</td>
            <td>:</td>
            <td colspan="2">{{ $form['free_legalitas'] }}</td>
        </tr>
        <tr>
            <td>Bonus</td>
            <td>:</td>
            <td colspan="2">{{ $form['bonus'] }}</td>
        </tr>
    </table>

    <div class="divider-line"></div>

    <!-- PENGAJUAN CUSTOMER -->
    <div class="bold-title">PENGAJUAN</div>
    <table class="data-table">
        <tr>
            <td style="width: 110px;">Cara bayar</td>
            <td style="width: 15px;">:</td>
            <td colspan="2"><strong>{{ $form['pengajuan']['cara_bayar'] }}</strong></td>
        </tr>
        <tr>
            <td>Pengajuan Price</td>
            <td>:</td>
            <td colspan="2"><strong>{{ $form['pengajuan']['price'] }}</strong></td>
        </tr>
        <tr>
            <td>Reservasi</td>
            <td>:</td>
            <td style="width: 200px;">{{ $form['pengajuan']['reservasi'] }}</td>
            <td>Tanggal : {{ $form['pengajuan']['reservasi_date'] }}</td>
        </tr>
        <tr>
            <td>Booking Fee</td>
            <td>:</td>
            <td>{{ $form['pengajuan']['booking_fee'] }} {{ $form['pengajuan']['booking_fee_total'] }}</td>
            <td>Tanggal : {{ $form['pengajuan']['booking_fee_date'] }}</td>
        </tr>
        <tr>
            <td>DP1 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; %</td>
            <td>:</td>
            <td>{{ $form['pengajuan']['dp1_amount'] }}</td>
            <td>Tanggal : {{ $form['pengajuan']['dp1_date'] }}</td>
        </tr>
        <tr>
            <td>DP2</td>
            <td>:</td>
            <td>{{ $form['pengajuan']['dp2_amount'] }}</td>
            <td>Tanggal : {{ $form['pengajuan']['dp2_date'] }}</td>
        </tr>
        <tr>
            <td>Pelunasan</td>
            <td>:</td>
            <td>{{ $form['pengajuan']['pelunasan_amount'] }}</td>
            <td>Tanggal : {{ $form['pengajuan']['pelunasan_date'] }}</td>
        </tr>
    </table>

    <div class="divider-line"></div>

    <!-- NOTE PENGAJUAN -->
    <div style="font-weight: bold; font-size: 8.5pt; margin-bottom: 3px;">Note :</div>
    <ol class="note-list">
        @foreach($form['pengajuan']['notes'] as $n)
            @if(!empty(trim($n)))
                <li>{{ $n }}</li>
            @endif
        @endforeach
    </ol>


    <!-- ========================================== -->
    <!-- HALAMAN 2: JAWABAN (DEVELOPER COUNTER/APPROVAL) -->
    <!-- ========================================== -->
    <div class="page-break"></div>

    <div class="header-logo-container">
        @if($logoBase64)
            <img src="{{ $logoBase64 }}" class="header-logo" alt="Logo" />
        @endif
    </div>

    <div class="divider-line" style="margin-top: 4px; margin-bottom: 12px;"></div>

    <div class="bold-title" style="margin-bottom: 8px;">JAWABAN</div>
    <table class="data-table">
        <tr>
            <td style="width: 110px;">Cara bayar</td>
            <td style="width: 15px;">:</td>
            <td colspan="2"><strong>{{ $form['jawaban']['cara_bayar'] }}</strong></td>
        </tr>
        <tr>
            <td>Pengajuan Price</td>
            <td>:</td>
            <td colspan="2"><strong>{{ $form['jawaban']['price'] }}</strong></td>
        </tr>
        <tr>
            <td>Reservasi</td>
            <td>:</td>
            <td style="width: 200px;">{{ $form['jawaban']['reservasi'] }}</td>
            <td>Tanggal : {{ $form['jawaban']['reservasi_date'] }}</td>
        </tr>
        <tr>
            <td>Booking Fee</td>
            <td>:</td>
            <td>{{ $form['jawaban']['booking_fee'] }} {{ $form['jawaban']['booking_fee_total'] }}</td>
            <td>Tanggal : {{ $form['jawaban']['booking_fee_date'] }}</td>
        </tr>
        <tr>
            <td>DP1 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; %</td>
            <td>:</td>
            <td>{{ $form['jawaban']['dp1_amount'] }}</td>
            <td>Tanggal : {{ $form['jawaban']['dp1_date'] }}</td>
        </tr>
        <tr>
            <td>DP2</td>
            <td>:</td>
            <td>{{ $form['jawaban']['dp2_amount'] }}</td>
            <td>Tanggal : {{ $form['jawaban']['dp2_date'] }}</td>
        </tr>
        <tr>
            <td>Pelunasan</td>
            <td>:</td>
            <td>{{ $form['jawaban']['pelunasan_amount'] }}</td>
            <td>Tanggal : {{ $form['jawaban']['pelunasan_date'] }}</td>
        </tr>
    </table>

    <div class="divider-line" style="margin-top: 10px; margin-bottom: 10px;"></div>

    <!-- NOTE JAWABAN -->
    <div style="font-weight: bold; font-size: 8.5pt; margin-bottom: 4px;">Note :</div>
    <ol class="note-list" style="line-height: 1.35;">
        @foreach($form['jawaban']['notes'] as $jn)
            @if(!empty(trim($jn)))
                <li style="margin-bottom: 5px;">{{ $jn }}</li>
            @endif
        @endforeach
    </ol>

    <!-- LEMBAR TANDA TANGAN -->
    <div style="margin-top: 18px; font-size: 8.5pt;">{{ $form['jawaban']['sig_city_date'] }}</div>
    <table class="sig-table">
        <tr>
            <td>
                <div>Yang Mengajukan,</div>
                <div class="sig-box">
                    @if(!empty($negotiation->client_signature))
                        <img src="{{ $negotiation->client_signature }}" class="sig-img" />
                    @endif
                </div>
                <div>( {{ $form['jawaban']['sig_pengaju_name'] }} )</div>
            </td>
            <td>
                <div>Mengetahui,</div>
                <div class="sig-box">
                    @if($sigMaulizarBase64)
                        <img src="{{ $sigMaulizarBase64 }}" class="sig-img" />
                    @endif
                </div>
                <div>( {{ $form['jawaban']['sig_mengetahui_name'] }} )</div>
            </td>
            <td>
                <div>Menyetujui,</div>
                <div class="sig-box">
                    @if($sigBramantyoBase64)
                        <img src="{{ $sigBramantyoBase64 }}" class="sig-img" />
                    @endif
                </div>
                <div>( {{ $form['jawaban']['sig_menyetujui_name'] }} )</div>
            </td>
        </tr>
    </table>

</body>
</html>
