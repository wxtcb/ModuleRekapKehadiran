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

    <form method="GET" class="row mb-3">
        {{-- Form Pencarian Nama --}}
        <div class="col-md-5">
            <label for="nama">Cari Nama Pegawai:</label>
            <input 
                type="text" 
                name="nama" 
                id="nama" 
                class="form-control" 
                placeholder="Masukkan nama pegawai..."
                value="{{ request('nama') }}"
                oninput="this.form.submit()"
            >
        </div>
    {{-- Form Pilih Tanggal --}}
    <div class="col-md-3">
        <label for="tanggal">Pilih Tanggal:</label>
        <input 
            type="date" 
            name="tanggal" 
            id="tanggal" 
            class="form-control"
            max="{{ date('Y-m-d') }}"
            value="{{ request('tanggal', date('Y-m-d')) }}"
            onchange="this.form.submit()"
        >
    </div>

</form>


    <div class="mt-2">
        @include('layouts.partials.messages')
    </div>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Nama</th>
                <th>NIP</th>
                <th>Jam Masuk</th>
                <th>Jam Pulang</th>
                <th>Status</th>
                <th>Waktu Kerja</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rekapPresensi as $index => $data)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $data->nip }}</td>
                <td>{{ $data->nama }}</td>
                <td>{{ $data->waktu_datang }}</td>
                <td>{{ $data->waktu_pulang }}</td>
                <td>{{ $data->status }}</td>
                <td>{{ $data->durasi_jam }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

        </div>
    </div>
</div>
@stop