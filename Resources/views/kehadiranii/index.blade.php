@extends('adminlte::page')
@section('title', 'Kehadiran Pegawai II')
@section('content_header')
<h1 class="m-0 text-dark"></h1>
@stop
@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h3>Rekapitulasi Kehadiran Pegawai II</h3>
                <div class="lead">

                </div>

                <div class="mt-2">
                    @include('layouts.partials.messages')
                </div>

                <style>
    .small-font {
        font-size: 12px;
    }

    .scroll-table-wrapper {
        overflow-x: auto;
        width: 100%;
    }

    .table td, .table th {
        white-space: nowrap;
    }
</style>

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
    <ul style="list-style-type: none; padding-left: 0;">
        <li>
            <span style="display: inline-block; width: 20px; height: 20px; background-color: #00b050; margin-right: 5px;"></span>
            <strong>D</strong> : Datang
        </li>
        <li>
            <span style="display: inline-block; width: 20px; height: 20px; background-color: #ff0000; margin-right: 5px;"></span>
            <strong>TM</strong> : Terlambat Masuk
        </li>
    </ul>
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
                            {{ $presensi == 'D' ? '#00b050' : '#ff0000' }};
                            color: white; text-align: center;">
                            {{ $presensi }}
                        </td>
                    @endforeach
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