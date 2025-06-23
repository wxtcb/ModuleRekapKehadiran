<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rekap Kehadiran {{ $pegawai->nama }} - {{ $monthName }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 14px;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .header p {
            margin: 2px 0;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        table, th, td {
            border: 1px solid #000;
        }
        th, td {
            padding: 5px;
            text-align: left;
            font-size: 10px;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }
        .footer {
            margin-top: 15px;
            text-align: right;
            font-size: 10px;
        }
        .page-break {
            page-break-after: always;
        }
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>REKAPITULASI KEHADIRAN PEGAWAI</h1>
        <p>Periode: {{ $monthName }}</p>
        <p>Nama: {{ $pegawai->nama }}</p>
        <p>NIP: {{ $pegawai->nip }}</p>
    </div>

    <table>
        <thead>
            <tr>
                @foreach($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
                <tr>
                    <td class="text-center">{{ $row['nama'] }}</td>
                    <td class="text-center">{{ $row['nip'] }}</td>
                    <td class="text-center">{{ $row['tanggal'] }}</td>
                    <td class="text-center">{{ $row['waktu_datang'] }}</td>
                    <td class="text-center">{{ $row['waktu_pulang'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td class="text-center">{{ $row['durasi'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Dicetak pada: {{ now()->format('d-m-Y H:i:s') }}</p>
    </div>
</body>
</html>