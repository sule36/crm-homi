<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            margin: 12px 18px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8px;
            color: #1e293b;
            line-height: 1.2;
            background-color: #ffffff;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .font-black { font-weight: 900; }
        
        /* HEADER BAR MATCHING IMAGE 1 */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .project-title {
            font-size: 14px;
            font-weight: 900;
            color: #000000;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .project-subtitle {
            font-size: 9px;
            font-weight: 800;
            color: #000000;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .doc-version-tag {
            font-size: 7.5px;
            font-weight: bold;
            color: #ffffff;
            background-color: #0f172a;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
            text-transform: uppercase;
        }

        /* PRICE LIST MAIN TABLE (IMAGE 1 REPLICA) */
        .pl-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .pl-table th {
            background-color: #0b1e36;
            color: #ffffff;
            font-size: 7px;
            font-weight: 900;
            text-transform: uppercase;
            padding: 5px 3px;
            border: 1px solid #000000;
            text-align: center;
        }
        .pl-table td {
            padding: 3px 4px;
            border: 1px solid #000000;
            font-size: 7.5px;
        }
        
        /* Highlight row styling matching Image 1 */
        .row-gold {
            background-color: #c98a18 !important;
            color: #000000;
            font-weight: bold;
        }

        .badge-status {
            font-weight: 900;
            font-size: 7.5px;
            text-transform: uppercase;
            text-align: center;
        }

        /* FOOTER SECTIONS (IMAGE 1 REPLICA) */
        .footer-note-header {
            font-size: 7.5px;
            font-style: italic;
            margin-bottom: 4px;
            font-weight: bold;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7px;
            color: #000000;
        }
        .footer-table td {
            vertical-align: top;
            padding: 0 4px;
        }
        .footer-section-title {
            font-weight: bold;
            margin-bottom: 2px;
            font-size: 7.5px;
        }
        .footer-list {
            margin: 0;
            padding-left: 12px;
            line-height: 1.25;
        }
        
        .bank-box {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 4px;
            text-align: center;
            font-weight: 900;
            font-size: 9px;
            background-color: #f8fafc;
        }

        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>

    <!-- SECTION 1: SITE PLAN GRAPHIC MAP VIEW (If mode is 'siteplan' or 'combined') -->
    @if(in_array($mode, ['siteplan', 'combined']))
        <table class="header-table">
            <tr>
                <td style="width: 25%; vertical-align: middle;">
                    <div style="font-size: 11px; font-weight: 900; color: #000000;">PT. SERANGKAI RODEN DEVELOPMENT</div>
                </td>
                <td style="width: 50%; text-align: center; vertical-align: middle;">
                    <div class="project-title">INTERACTIVE SITE PLAN {{ strtoupper($project->name) }}</div>
                    <div class="project-subtitle">{{ strtoupper($project->location) }}</div>
                </td>
                <td style="width: 25%; text-align: right; vertical-align: middle;">
                    <span class="doc-version-tag">VERSI {{ strtoupper($version) }}</span>
                    <div style="font-size: 7px; margin-top: 2px; color: #475569;">Doc ID: {{ $docCode }}</div>
                </td>
            </tr>
        </table>

        <!-- REAL GRAPHIC SITE PLAN IMAGE -->
        @if($project->siteplan_image && file_exists(public_path('storage/' . $project->siteplan_image)))
            <div style="text-align: center; margin-bottom: 10px;">
                <img src="{{ public_path('storage/' . $project->siteplan_image) }}" style="max-width: 100%; max-height: 480px; border-radius: 8px; border: 1.5px solid #0f172a;" />
            </div>
        @else
            <!-- SUMMARY STOK BAR -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                <tr>
                    <td style="padding: 6px; background: #f1f5f9; border: 1px solid #cbd5e1; text-align: center;"><strong>TOTAL UNIT:</strong> {{ $stats['total'] }}</td>
                    <td style="padding: 6px; background: #d1fae5; border: 1px solid #6ee7b7; text-align: center; color: #065f46;"><strong>AVAILABLE:</strong> {{ $stats['available'] }}</td>
                    <td style="padding: 6px; background: #fef3c7; border: 1px solid #fcd34d; text-align: center; color: #92400e;"><strong>RESERVED:</strong> {{ $stats['reserved'] }}</td>
                    <td style="padding: 6px; background: #e0e7ff; border: 1px solid #a5b4fc; text-align: center; color: #3730a3;"><strong>BOOKED:</strong> {{ $stats['booked'] }}</td>
                    <td style="padding: 6px; background: #f1f5f9; border: 1px solid #cbd5e1; text-align: center; color: #334155;"><strong>SOLD OUT:</strong> {{ $stats['sold'] }}</td>
                </tr>
            </table>
        @endif
    @endif

    @if($mode === 'combined')
        <div class="page-break"></div>
    @endif

    <!-- SECTION 2: OFFICIAL PRICE LIST TABLE (EXACT MATCH FOR IMAGE 1) -->
    @if(in_array($mode, ['pricelist', 'combined']))
        <table class="header-table">
            <tr>
                <td style="width: 25%; vertical-align: middle;">
                    <div style="font-size: 10px; font-weight: 900; color: #000000; text-transform: uppercase;">
                        PT. SERANGKAI RODEN DEVELOPMENT
                    </div>
                </td>
                <td style="width: 50%; text-align: center; vertical-align: middle;">
                    <div class="project-title">PRICE LIST {{ strtoupper($project->name) }}</div>
                    <div class="project-subtitle">{{ strtoupper($project->location) }}</div>
                </td>
                <td style="width: 25%; text-align: right; vertical-align: middle;">
                    <span class="doc-version-tag">VERSI {{ strtoupper($version) }}</span>
                    <div style="font-size: 7px; margin-top: 2px; color: #475569;">Doc ID: {{ $docCode }}</div>
                    <div style="font-size: 7px; color: #475569;">Dicetak: {{ $printedAt }}</div>
                </td>
            </tr>
        </table>

        <table class="pl-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 3%;">NO.</th>
                    <th rowspan="2" style="width: 5%;">BLOK</th>
                    <th colspan="2" style="width: 9%;">TYPE</th>
                    <th rowspan="2" style="width: 15%;">CASH KERAS</th>
                    <th rowspan="2" style="width: 13%;">BOOKING FEE</th>
                    <th rowspan="2" style="width: 13%;">DP 10%</th>
                    <th colspan="2" style="width: 28%;">ANGSURAN CASH BERTAHAP</th>
                    <th rowspan="2" style="width: 14%;">KETERANGAN</th>
                </tr>
                <tr>
                    <th style="width: 4.5%;">LB</th>
                    <th style="width: 4.5%;">LT</th>
                    <th style="width: 14%;">CICILAN 18x</th>
                    <th style="width: 14%;">CICILAN 24x</th>
                </tr>
            </thead>
            <tbody>
                @php $no = 1; @endphp
                @foreach($units as $u)
                    @php
                        $price = $u->final_price;
                        $bookingFee = 25000000;
                        $dp10 = $price * 0.10;
                        $sisaPlafon = max(0, $price - $bookingFee);
                        $cicilan18 = $sisaPlafon / 18;
                        $cicilan24 = $sisaPlafon / 24;

                        // Check if lot is special corner/hook/sold/booked row to apply gold background matching Image 1
                        $isGoldRow = in_array(strtoupper($u->status), ['SOLD', 'BOOKED', 'RESERVED']) || in_array($u->block . $u->number, ['A1', 'A2', 'A10', 'B1', 'B11', 'C1', 'C10', 'D1', 'D10']);
                    @endphp
                    <tr class="{{ $isGoldRow ? 'row-gold' : '' }}">
                        <td class="text-center">{{ $no++ }}</td>
                        <td class="text-center font-black">{{ $u->block }}{{ $u->number }}</td>
                        <td class="text-center font-bold">{{ $u->unitType?->building_area ?? 198 }}</td>
                        <td class="text-center font-bold">{{ $u->unitType?->land_area ?? 105 }}</td>
                        <td class="text-right">Rp {{ number_format($price, 2, '.', ',') }}</td>
                        <td class="text-right">Rp {{ number_format($bookingFee, 2, '.', ',') }}</td>
                        <td class="text-right">Rp {{ number_format($dp10, 2, '.', ',') }}</td>
                        <td class="text-right">Rp {{ number_format($cicilan18, 2, '.', ',') }}</td>
                        <td class="text-right">Rp {{ number_format($cicilan24, 2, '.', ',') }}</td>
                        <td class="text-center font-black badge-status">
                            @if($u->status === 'sold')
                                SOLD
                            @elseif($u->status === 'booked')
                                BOOKED
                            @elseif($u->status === 'reserved')
                                RESERVED
                            @elseif($u->status === 'hold')
                                HOLD
                            @else
                                {{ strtoupper($u->status) }}
                            @endif

                            @if($version === 'internal' && $u->net_price)
                                <div style="font-size: 6.5px; font-weight: normal; margin-top: 1px;">Net: Rp {{ number_format($u->net_price, 0, ',', '.') }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- FOOTER DETAILS (EXACT REPLICA OF IMAGE 1 FOOTER) -->
        <div class="footer-note-header">*Berlaku hingga : {{ date('t F Y', strtotime('now')) }}</div>

        <table class="footer-table">
            <tr>
                <!-- COLUMN 1: CATATAN -->
                <td style="width: 42%;">
                    <div class="footer-section-title">Catatan:</div>
                    <ol class="footer-list">
                        <li>Harga diatas sudah termasuk BPHTB, PPN, AJB, SHM, Smart Door Lock, Kanopi, Sanitari, dan Taman Depan</li>
                        <li>Harga diatas belum termasuk additional yang diajukan oleh Pembeli</li>
                        <li>Harga dan ketersediaan unit tidak mengikat sebelum pembayaran Booking Fee</li>
                        <li>Booking Fee dianggap hangus apabila terdapat pembatalan sepihak dari pembeli</li>
                        <li>Pembayaran Down Payment (DP) dapat dilunasi paling lambat 14 hari semenjak Booking Fee dibayarkan</li>
                        <li>Pembayaran yang diakui adalah yang memiliki BUKTI KUITANSI / TRANSFER resmi ke Developer, yaitu:<br>
                            <strong>a.n PT. SERANGKAI RODEN DEVELOPMENT, BRI: 012001004640307</strong>
                        </li>
                        <li>Serah Terima Unit dilakukan maksimal 12 bulan setelah pembangunan dimulai</li>
                    </ol>
                </td>

                <!-- COLUMN 2: TAHAPAN PEMESANAN -->
                <td style="width: 35%;">
                    <div class="footer-section-title">Tahapan Pemesanan:</div>
                    <ol class="footer-list">
                        <li>Melakukan Booking Fee terhadap unit yang dipilih</li>
                        <li>Melengkapi dokumen persyaratan yang diperlukan</li>
                        <li>Mengisi SPR (Surat Pemesanan Rumah) sebagai bukti pemesanan unit</li>
                        <li>Pembayaran DP sesuai dengan skema yang telah disepakati</li>
                        <li>Penandatanganan PPJB (Perjanjian Pengikatan Jual Beli)</li>
                        <li>Pelunasan Angsuran sesuai dengan skema yang disepakati</li>
                        <li>Serah Terima Unit</li>
                    </ol>
                </td>

                <!-- COLUMN 3: BANK KERJASAMA -->
                <td style="width: 23%;">
                    <div class="footer-section-title">Bank Kerjasama:</div>
                    <table style="width: 100%; border-collapse: separate; border-spacing: 3px;">
                        <tr>
                            <td class="bank-box" style="color: #00529c;">BRI</td>
                            <td class="bank-box" style="color: #00a39e;">BSI</td>
                        </tr>
                        <tr>
                            <td class="bank-box" style="color: #003366;">mandırı</td>
                            <td class="bank-box" style="color: #002d62;">Bank BTN</td>
                        </tr>
                    </table>
                    <div style="font-size: 6.5px; color: #475569; margin-top: 6px; text-align: center; font-style: italic;">
                        Price & availability subject to latest update on Developer CRM.
                    </div>
                </td>
            </tr>
        </table>
    @endif

</body>
</html>
