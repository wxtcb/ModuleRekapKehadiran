@extends('adminlte::page')
@section('title', 'Kehadiran Pegawai II')
@section('content_header')
<h1 class="m-0 text-dark"></h1>
@stop
@section('content')
<div class="row">

    <style>
        .small-font {
            font-size: 12px;
        }

        .scroll-table-wrapper {
            overflow-x: auto;
            width: 100%;
        }

        .table td,
        .table th {
            white-space: nowrap;
        }

        .dropdown-export {
            display: inline-block;
            position: relative;
        }

        .dropdown-export-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: #f9f9f9;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
            z-index: 1;
        }

        .dropdown-export-content a {
            color: black;
            padding: 8px 12px;
            text-decoration: none;
            display: block;
            font-size: 14px;
        }

        .dropdown-export-content a:hover {
            background-color: #f1f1f1;
        }

        .dropdown-export:hover .dropdown-export-content {
            display: block;
        }
    </style>

    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="mb-0">Rekapitulasi Kehadiran Pegawai II</h3>

                    <div class="dropdown-export">
                        <button class="btn btn-info btn-sm dropdown-toggle" type="button">
                            <i class="fas fa-download"></i> Unduh Laporan
                        </button>
                        <div class="dropdown-export-content">
                            <a href="#" id="download-excel-btn">
                                <i class="fas fa-file-excel text-success"></i> Excel
                            </a>
                            <a href="#" id="download-pdf-btn">
                                <i class="fas fa-file-pdf text-danger"></i> PDF
                            </a>
                        </div>
                    </div>
                </div>

                <div class="lead"></div>

                <div class="mt-2">
                    @include('layouts.partials.messages')
                </div>


                <form method="GET" id="filter-form" class="mb-3 d-flex align-items-center gap-2 small-font">
                    <select name="month" id="month" class="form-control" onchange="document.getElementById('filter-form').submit()">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>
                            {{ DateTime::createFromFormat('!m', $m)->format('F') }}
                            </option>
                            @endfor
                    </select>

                    <select name="year" id="year" class="form-control" onchange="document.getElementById('filter-form').submit()">
                        @for ($y = now()->year; $y >= now()->year - 5; $y--)
                        <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </form>

                <div class="mb-3 small-font">
                    <strong>Keterangan:</strong>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;">
                        <!-- D -->
                        <div style="display: flex; align-items: center; width: 250px;">
                            <div style="width: 50px; height: 40px; background-color: #00b050; color: white; display: flex; justify-content: center; align-items: center; font-weight: bold;">D / WFH</div>
                            <div style="margin-left: 10px;">Hadir / Dapat Tunjangan Kehadiran</div>
                        </div>

                        <!-- TM -->
                        <div style="display: flex; align-items: center; width: 250px;">
                            <div style="width: 50px; height: 40px; background-color: #ff0000; color: white; display: flex; justify-content: center; align-items: center; font-weight: bold;">TM</div>
                            <div style="margin-left: 10px;">Tidak Absensi / Presensi</div>
                        </div>

                        <!-- DL -->
                        <div style="display: flex; align-items: center; width: 250px;">
                            <div style="width: 50px; height: 40px; background-color: #800080; color: white; display: flex; justify-content: center; align-items: center; font-weight: bold;">DL</div>
                            <div style="margin-left: 10px;">Dinas Luar</div>
                        </div>

                        <!-- T -->
                        <div style="display: flex; align-items: center; width: 250px;">
                            <div style="width: 50px; height: 40px; background-color: #ffa500; color: white; display: flex; justify-content: center; align-items: center; font-weight: bold;">T / WFH</div>
                            <div style="margin-left: 10px;">Hadir / Tidak Dapat Tunjangan Kehadiran</div>
                        </div>

                        <!-- C -->
                        <div style="display: flex; align-items: center; width: 250px;">
                            <div style="width: 50px; height: 40px; background-color: #0000ff; color: white; display: flex; justify-content: center; align-items: center; font-weight: bold;">C</div>
                            <div style="margin-left: 10px;">Cuti</div>
                        </div>


                        <!-- L -->
                        <div style="display: flex; align-items: center; width: 250px;">
                            <div style="width: 50px; height: 40px; background-color: #808080; color: white; display: flex; justify-content: center; align-items: center; font-weight: bold;">L</div>
                            <div style="margin-left: 10px;">Libur</div>
                        </div>
                    </div>
                </div>

                <div class="scroll-table-wrapper">
                    <table class="table table-bordered small-font">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>NIP</th>
                                <th>Nama</th>
                                <th>Keterangan</th>
                                @foreach($tanggalHari as $tgl)
                                <th>{{ \Carbon\Carbon::parse($tgl)->format('d') }}</th>
                                @endforeach
                                <th>D</th>
                                <th>TM</th>
                                <th>C</th>
                                <th>T</th>
                                <th>DL</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data as $index => $pegawai)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $pegawai['nip'] }}</td>
                                <td>{{ $pegawai['nama'] }}</td>
                                <td>{{ $pegawai['keterangan'] }}

                                @foreach($pegawai['presensi'] as $idx => $status)
                                @php
                                $jamMasuk = $pegawai['jam_masuk'][$idx] ?? '-';
                                $jamPulang = $pegawai['jam_pulang'][$idx] ?? '-';
                                $warna = match($status) {
                                'D' => '#00b050',
                                'TM' => '#ff0000',
                                'L' => '#808080',
                                'C' => '#0000ff',
                                'T' => '#ffa500',
                                'DL' => '#800080',
                                default => 'transparent'
                                };
                                @endphp
                                <td style="background-color: {{ $warna }}; color: white; font-size: 11px; text-align: center;">
                                    {{ $jamMasuk }}<br>{{ $jamPulang }}
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
                </div>

            </div>
        </div>
    </div>
</div>
@stop
@section('adminlte_js')

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const monthSelect = document.getElementById('month');
        const yearSelect = document.getElementById('year');
        const excelBtn = document.getElementById('download-excel-btn');
        const pdfBtn = document.getElementById('download-pdf-btn');

        function updateDownloadLinks() {
            const month = monthSelect.value;
            const year = yearSelect.value;
            const baseUrl = "{{ route('rekap-bulanan.export') }}";

            excelBtn.href = `${baseUrl}?month=${month}&year=${year}&format=excel`;
            pdfBtn.href = `${baseUrl}?month=${month}&year=${year}&format=pdf`;
        }

        // Tambahkan event listener untuk mencegah default behavior
        [excelBtn, pdfBtn].forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = this.href;
            });
        });

        monthSelect.addEventListener('change', updateDownloadLinks);
        yearSelect.addEventListener('change', updateDownloadLinks);
        updateDownloadLinks();
    });
</script>

@stop