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

                <form method="GET" action="" class="mb-3" id="filter-form">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <!-- Pilih Tahun -->
                        <div class="d-flex align-items-center mb-2 mb-md-0">
                            <label for="year" class="me-2 col-form-label">Pilih Tahun:</label>
                            <select name="year" id="year" class="form-select w-auto">
                                @for($i = now()->year; $i >= 2020; $i--)
                                    <option value="{{ $i }}" {{ $i == $year ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>

                        <!-- Tombol Unduh -->
                        <div>
                            <button type="button" id="download-btn" class="btn btn-info">
                                Unduh Excel
                            </button>
                        </div>
                    </div>
                </form>

                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>NIP/NIK/NIPPK</th>
                            <th>Nama</th>
                            <th>Keterangan</th>
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
                            <td>{{ $item['jenis'] }}</td>
                            <td>{{ $item['total']['D'] }}</td>
                            <td>{{ $item['total']['T'] }}</td>
                            <td>{{ $item['total']['TM'] }}</td>
                            <td>{{ $item['total']['C'] }}</td>
                            <td>{{ $item['total']['DL'] }}</td>
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
@section('adminlte_js')

<script>
    // Submit otomatis saat tahun diubah
    document.getElementById('year').addEventListener('change', function() {
        document.getElementById('filter-form').submit();
    });

    // Unduh file Excel sesuai tahun
    document.getElementById('download-btn').addEventListener('click', function() {
        const year = document.getElementById('year').value;
        const url = `{{ route('rekap-tahunan.export') }}?year=${year}`;
        window.location.href = url;
    });
</script>

@stop