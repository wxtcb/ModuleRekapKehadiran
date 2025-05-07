@extends('adminlte::page')
@section('title', 'Kehadiran Pegawai III')
@section('content_header')
<h1 class="m-0 text-dark"></h1>
@stop
@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h3>Rekapitulasi Kehadiran Pegawai III</h3>
                <div class="lead">

                </div>

                <div class="mt-2">
                    @include('layouts.partials.messages')
                </div>

                <form method="GET" class="mb-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <label for="year" class="col-form-label">Pilih Tahun:</label>
                        </div>
                        <div class="col-auto">
                            <select name="year" id="year" class="form-select" onchange="this.form.submit()">
                                @for($i = now()->year; $i >= 2020; $i--)
                                <option value="{{ $i }}" {{ $i == $year ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                </form>

                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>NIP/NIK/NIPPK</th>
                            <th>Nama</th>
                            <th>D</th> {{-- Hadir (dapat tunjangan) --}}
                            <th>T</th> {{-- Hadir (tanpa tunjangan) --}}
                            <th>TM</th> {{-- Tidak absen --}}
                            <th>C</th> {{-- Cuti --}}
                            <th>DL</th> {{-- Dinas Luar --}}
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item['nip'] }}</td>
                            <td>{{ $item['nama'] }}</td>
                            <td>{{ $item['total']['D'] }}</td>
                            <td>0</td>
                            <td>{{ $item['total']['TM'] }}</td>
                            <td>0</td>
                            <td>0</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9">Data tidak tersedia untuk tahun ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>

            </div>
        </div>
    </div>
</div>
@stop