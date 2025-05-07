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
    </style>

    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h3>Rekapitulasi Kehadiran Pegawai II</h3>
                <div class="lead">

                </div>

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
                                @foreach($pegawai['presensi'] as $presensi)
                                <td style="background-color:
                                    {{ $presensi == 'D' ? '#00b050' :
                                    ($presensi == 'TM' ? '#ff0000' :
                                    ($presensi == 'L' ? '#808080' :
                                    ($presensi == 'C' ? '#0000ff' :
                                    ($presensi == 'T' ? '#ffa500' :
                                    ($presensi == 'DL' ? '#800080' : 'transparent'))))) }};
                                    color: white; text-align: center;">
                                    {{ $presensi }}
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