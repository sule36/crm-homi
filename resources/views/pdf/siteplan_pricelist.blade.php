<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            margin: 15px 20px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
            line-height: 1.3;
            background-color: #ffffff;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
        }
        .header-logo {
            font-size: 18px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -0.5px;
        }
        .header-subtitle {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
        }
        .doc-meta {
            text-align: right;
            font-size: 8px;
            color: #475569;
        }
        .version-badge {
            display: inline-block;
            padding: 3px 8px;
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
            border-radius: 4px;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Stats Bar */
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .stats-cell {
            padding: 6px 10px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            text-align: center;
        }
        .stats-val {
            font-size: 14px;
            font-weight: 900;
            color: #0f172a;
            display: block;
        }
        .stats-lbl {
            font-size: 7.5px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
        }
        .badge-available { background-color: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .badge-reserved { background-color: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .badge-booked { background-color: #e0e7ff; color: #3730a3; border: 1px solid #a5b4fc; }
        .badge-sold { background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
        .badge-hold { background-color: #ffe4e6; color: #9f1239; border: 1px solid #fca5a5; }

        /* Block Grid Cards for Site Plan */
        .block-title {
            font-size: 11px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            margin-top: 10px;
            margin-bottom: 6px;
            padding-bottom: 3px;
            border-bottom: 1.5px solid #cbd5e1;
        }
        .lot-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 4px;
            margin-bottom: 12px;
        }
        .lot-card {
            padding: 6px;
            border-radius: 5px;
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            vertical-align: top;
            width: 16%;
        }

        /* Price List Table */
        .pl-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 12px;
        }
        .pl-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
            padding: 6px 8px;
            border: 1px solid #0f172a;
            text-align: left;
        }
        .pl-table td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            font-size: 8.5px;
        }
        .pl-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .font-black { font-weight: 900; }

        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #cbd5e1;
            font-size: 7.5px;
            color: #64748b;
            width: 100%;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>

    <!-- HEADER BAR -->
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <div class="header-logo">{{ strtoupper($project->name) }}</div>
                <div class="header-subtitle">
                    {{ $project->location }} — {{ $project->address }}
                </div>
            </td>
            <td class="doc-meta">
                <span class="version-badge">VERSI {{ strtoupper($version) }}</span>
                <div style="margin-top: 4px; font-weight: bold; color: #0f172a;">Doc ID: {{ $docCode }}</div>
                <div style="margin-top: 2px;">Dicetak: {{ $printedAt }}</div>
            </td>
        </tr>
    </table>

    <!-- SUMMARY STOK RINGKAS -->
    <table class="stats-table">
        <tr>
            <td class="stats-cell">
                <span class="stats-val">{{ $stats['total'] }}</span>
                <span class="stats-lbl">Total Unit</span>
            </td>
            <td class="stats-cell" style="background-color: #ecfdf5; border-color: #a7f3d0;">
                <span class="stats-val" style="color: #047857;">{{ $stats['available'] }}</span>
                <span class="stats-lbl" style="color: #047857;">Available</span>
            </td>
            <td class="stats-cell" style="background-color: #fffbeb; border-color: #fde68a;">
                <span class="stats-val" style="color: #b45309;">{{ $stats['reserved'] }}</span>
                <span class="stats-lbl" style="color: #b45309;">Reserved</span>
            </td>
            <td class="stats-cell" style="background-color: #eef2ff; border-color: #c7d2fe;">
                <span class="stats-val" style="color: #4338ca;">{{ $stats['booked'] }}</span>
                <span class="stats-lbl" style="color: #4338ca;">Booked</span>
            </td>
            <td class="stats-cell">
                <span class="stats-val" style="color: #334155;">{{ $stats['sold'] }}</span>
                <span class="stats-lbl">Sold Out</span>
            </td>
            <td class="stats-cell" style="background-color: #fff1f2; border-color: #fecdd3;">
                <span class="stats-val" style="color: #be123c;">{{ $stats['hold'] }}</span>
                <span class="stats-lbl" style="color: #be123c;">Hold</span>
            </td>
        </tr>
    </table>

    <!-- SECTION 1: SITE PLAN VISUAL GRID (If mode is 'siteplan' or 'combined') -->
    @if(in_array($mode, ['siteplan', 'combined']))
        <div style="margin-bottom: 10px;">
            <div style="font-size: 13px; font-weight: 900; color: #0f172a; text-transform: uppercase;">
                🗺️ Interactive Site Plan Summary
            </div>
            <div style="font-size: 8px; color: #64748b; margin-top: 1px;">
                Status ketersediaan unit aktual proyek {{ $project->name }}
            </div>
        </div>

        @foreach($unitsByBlock as $bName => $bUnits)
            <div class="block-title">BLOK KAVLING {{ $bName }} (Total {{ count($bUnits) }} Unit)</div>
            <table class="lot-grid">
                <tr>
                @foreach($bUnits as $index => $u)
                    @if($index > 0 && $index % 6 == 0)
                        </tr><tr>
                    @endif
                    <td class="lot-card">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                            <span style="font-size: 11px; font-weight: 900; color: #0f172a;">{{ $u->block }}{{ $u->number }}</span>
                            <span class="badge badge-{{ $u->status }}">{{ strtoupper($u->status) }}</span>
                        </div>
                        <div style="font-size: 8px; color: #475569;">
                            LT {{ $u->unitType?->land_area ?? '-' }}m² | LB {{ $u->unitType?->building_area ?? '-' }}m²
                        </div>
                        <div style="font-size: 9.5px; font-weight: 900; color: #047857; margin-top: 3px;">
                            Rp {{ number_format($u->final_price, 0, ',', '.') }}
                        </div>
                        @if($u->promo)
                            <div style="font-size: 7.5px; color: #b45309; font-weight: bold; margin-top: 2px;">
                                🎁 {{ $u->promo }}
                            </div>
                        @endif
                    </td>
                @endforeach
                </tr>
            </table>
        @endforeach
    @endif

    <!-- PAGE BREAK FOR COMBINED MODE -->
    @if($mode === 'combined')
        <div class="page-break"></div>

        <!-- RE-PRINT MINI HEADER ON PAGE 2 -->
        <table class="header-table">
            <tr>
                <td style="width: 60%;">
                    <div class="header-logo">{{ strtoupper($project->name) }} — PRICE LIST OFFICIAL</div>
                    <div class="header-subtitle">Halaman Lampiran Tabel Daftar Harga & Spesifikasi Unit</div>
                </td>
                <td class="doc-meta">
                    <span class="version-badge">VERSI {{ strtoupper($version) }}</span>
                    <div style="margin-top: 2px;">Doc ID: {{ $docCode }}</div>
                </td>
            </tr>
        </table>
    @endif

    <!-- SECTION 2: DYNAMIC PRICE LIST TABLE (If mode is 'pricelist' or 'combined') -->
    @if(in_array($mode, ['pricelist', 'combined']))
        <div style="margin-bottom: 6px;">
            <div style="font-size: 13px; font-weight: 900; color: #0f172a; text-transform: uppercase;">
                📋 Official Price List Inventory
            </div>
            <div style="font-size: 8px; color: #64748b; margin-top: 1px;">
                Daftar harga resmi real-time terkoneksi langsung dengan database CRM Developer
            </div>
        </div>

        <table class="pl-table">
            <thead>
                <tr>
                    <th style="width: 4%; text-align: center;">No</th>
                    <th style="width: 10%;">Kavling</th>
                    <th style="width: 22%;">Tipe Unit</th>
                    <th style="width: 8%; text-align: right;">LT (m²)</th>
                    <th style="width: 8%; text-align: right;">LB (m²)</th>
                    <th style="width: 12%; text-align: center;">Spesifikasi</th>
                    <th style="width: 10%;">Hadap</th>
                    <th style="width: 16%; text-align: right;">Harga Jual (Rp)</th>
                    @if($version === 'internal')
                        <th style="width: 14%; text-align: right;">Net Price (Rp)</th>
                    @endif
                    <th style="width: 15%;">Promo & Catatan</th>
                    <th style="width: 10%; text-align: center;">Status</th>
                </tr>
            </thead>
            <tbody>
                @php $no = 1; @endphp
                @foreach($units as $u)
                    <tr>
                        <td class="text-center">{{ $no++ }}</td>
                        <td class="font-black">{{ $u->label }}</td>
                        <td class="font-bold">{{ $u->unitType?->name ?? 'Tipe Standard' }}</td>
                        <td class="text-right font-bold">{{ $u->unitType?->land_area ?? '-' }}</td>
                        <td class="text-right font-bold">{{ $u->unitType?->building_area ?? '-' }}</td>
                        <td class="text-center">{{ $u->unitType?->bedrooms ?? 0 }}KT/{{ $u->unitType?->bathrooms ?? 0 }}KM • {{ $u->carport }} Cpt</td>
                        <td>{{ $u->facing_direction ?? 'Utara' }}</td>
                        <td class="text-right font-black" style="color: #047857; font-size: 9px;">
                            Rp {{ number_format($u->final_price, 0, ',', '.') }}
                        </td>
                        @if($version === 'internal')
                            <td class="text-right font-bold" style="color: #92400e;">
                                {{ $u->net_price ? 'Rp ' . number_format($u->net_price, 0, ',', '.') : '-' }}
                            </td>
                        @endif
                        <td>
                            @if($u->promo)
                                <div style="color: #b45309; font-weight: bold;">🎁 {{ $u->promo }}</div>
                            @endif
                            @if($version === 'internal' && $u->management_notes)
                                <div style="color: #475569; font-size: 7.5px;">Note: {{ $u->management_notes }}</div>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge badge-{{ $u->status }}">{{ strtoupper($u->status) }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- FOOTER DISCLAIMER -->
    <div class="footer">
        <table style="width: 100%;">
            <tr>
                <td style="width: 70%;">
                    <strong>PENTING / DISCLAIMER:</strong> Price & availability subject to latest update on Developer CRM.<br>
                    Dokumen ini digenerate secara otomatis oleh sistem CRM {{ $project->name }}. Data harga & status unit berlaku saat dokumen dicetak.
                </td>
                <td style="width: 30%; text-align: right; vertical-align: top;">
                    <strong>Homi Developer CRM System</strong><br>
                    Verified Document & Version Control
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
