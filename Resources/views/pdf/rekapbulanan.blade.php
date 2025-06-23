<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 5mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 6px;
            margin: 0;
            padding: 0;
        }

        .header {
            text-align: center;
            margin-bottom: 3px;
        }

        .header h2 {
            margin: 0;
            padding: 0;
            font-size: 10px;
        }

        .header div {
            font-size: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        table,
        th,
        td {
            border: 0.5px solid black;
        }

        th,
        td {
            padding: 1px;
            text-align: center;
            font-size: 6px;
            line-height: 1.1;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .nowrap {
            white-space: nowrap;
        }

        /* Warna status lebih transparan */
        .status-D {
            background-color: rgba(0, 176, 80, 0.7);
            color: black;
        }

        .status-TM {
            background-color: rgba(255, 0, 0, 0.7);
            color: white;
        }

        .status-L {
            background-color: rgba(128, 128, 128, 0.7);
            color: white;
        }

        .status-C {
            background-color: rgba(0, 0, 255, 0.7);
            color: white;
        }

        .status-T {
            background-color: rgba(255, 165, 0, 0.7);
            color: black;
        }

        .status-DL {
            background-color: rgba(128, 0, 128, 0.7);
            color: white;
        }

        /* Legend compact */
        .legend {
            margin-top: 5px;
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            font-size: 6px;
            justify-content: center;
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin-right: 5px;
        }

        .legend-color {
            width: 10px;
            height: 10px;
            margin-right: 2px;
            border: 0.5px solid #000;
        }

        /* Memastikan tidak ada cell yang melebar */
        td {
            max-width: 25px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>{{ $title }}</h2>
        <div>Dicetak pada: {{ now()->format('d/m/Y H:i:s') }}</div>
    </div>

    <div class="legend">
        <div class="legend-item">
            <div class="legend-color status-D"></div>
            <div>D</div>
        </div>
        <div class="legend-item">
            <div class="legend-color status-TM"></div>
            <div>TM</div>
        </div>
        <div class="legend-item">
            <div class="legend-color status-DL"></div>
            <div>DL</div>
        </div>
        <div class="legend-item">
            <div class="legend-color status-T"></div>
            <div>T</div>
        </div>
        <div class="legend-item">
            <div class="legend-color status-C"></div>
            <div>C</div>
        </div>
        <div class="legend-item">
            <div class="legend-color status-L"></div>
            <div>L</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:20px">No</th>
                <th style="width:50px">NIP</th>
                <th style="width:80px">Nama</th>
                <th style="width:50px">Ket.</th>
                @foreach($tanggalHari as $tgl)
                <th style="width:15px">{{ \Carbon\Carbon::parse($tgl)->format('d') }}</th>
                @endforeach
                <th style="width:10px">D</th>
                <th style="width:10px">TM</th>
                <th style="width:10px">C</th>
                <th style="width:10px">T</th>
                <th style="width:10px">DL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $index => $pegawai)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td class="nowrap">{{ $pegawai['nip'] }}</td>
                <td>{{ Str::limit($pegawai['nama'], 15) }}</td>
                <td>{{ Str::limit($pegawai['keterangan'], 3) }}</td>

                @foreach($pegawai['presensi'] as $idx => $status)
                @php
                $jamMasuk = $pegawai['jam_masuk'][$idx] ?? '-';
                $jamPulang = $pegawai['jam_pulang'][$idx] ?? '-';
                @endphp
                <td class="status-{{ $status }}" title="{{ $status }}">
                    <div style="line-height:1.1">{{ $jamMasuk }}</div>
                    <div style="line-height:1.1">{{ $jamPulang }}</div>
                </td>
                @endforeach

                <td>{{ $pegawai['total']['D'] }}</td>
                <td>{{ $pegawai['total']['TM'] }}</td>
                <td>{{ $pegawai['total']['C'] }}</td>
                <td>{{ $pegawai['total']['T'] }}</td>
                <td>{{ $pegawai['total']['DL'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>


</body>

</html>