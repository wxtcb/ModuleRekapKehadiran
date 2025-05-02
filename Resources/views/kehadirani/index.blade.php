@extends('adminlte::page')
@section('title', 'Kehadiran Pegawai I')
@section('content_header')
<h1 class="m-0 text-dark"></h1>
@stop
@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h3>Rekapitulasi Kehadiran Pegawai I</h3>
                <div class="lead">

                </div>

                <div class="mt-2">
                    @include('layouts.partials.messages')
                </div>

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>NIP</th>
                            <th>Nama</th>
                            <th>Tanggal</th>
                            <th>Jam Masuk</th>
                            <th>Jam Pulang</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rekapPresensi as $index => $data)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $data['nip'] }}</td>
                            <td>{{ $data['nama'] }}</td>
                            <td>{{ date('d-m-Y', strtotime($data['tanggal'])) }}</td>
                            <td>{{ $data['waktu_datang'] }}</td>
                            <td>{{ $data['waktu_pulang'] }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center">Tidak ada data kehadiran.</td>
                        </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>
        </div>
    </div>
</div>
@stop