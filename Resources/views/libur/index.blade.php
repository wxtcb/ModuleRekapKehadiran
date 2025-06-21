@extends('adminlte::page')
@section('title', 'Setting')
@section('content_header')
<h1 class="m-0 text-dark"></h1>
@stop
@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h3>Setting Hari Libur Pegawai</h3>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <!-- Dropdown Pilih Tahun -->
                    <form method="GET" action="{{ route('libur.index') }}" class="form-inline">
                        <label for="tahun" class="mr-2">Tahun:</label>
                        <select name="year" id="tahun" class="form-control" onchange="this.form.submit()">
                            @foreach($tahunTersedia as $th)
                            <option value="{{ $th }}" {{ $th == $year ? 'selected' : '' }}>{{ $th }}</option>
                            @endforeach
                        </select>
                    </form>

                    <!-- Tombol Tambah Hari Libur -->
                    <button class="btn btn-primary" data-toggle="modal" data-target="#modalTambahLibur">
                        Tambah Hari Libur
                    </button>
                </div>

                <!-- Modal -->
                <div class="modal fade" id="modalTambahLibur" tabindex="-1" role="dialog" aria-labelledby="modalTambahLiburLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <form method="POST" action="{{ route('libur.store') }}">
                            @csrf
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Tambah Hari Libur</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div id="libur-wrapper">
                                        <div class="libur-item mb-3 border p-2 rounded">
                                            <label>Tanggal</label>
                                            <input type="date" name="tanggal[]" class="form-control mb-2" required>
                                            <label>Keterangan</label>
                                            <input type="text" name="keterangan[]" class="form-control" placeholder="Contoh: Hari Raya" required>
                                        </div>
                                    </div>

                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="btnTambahForm">
                                        + Tambah Form Hari Libur
                                    </button>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-primary">Simpan</button>
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                @include('layouts.partials.messages')

                <!-- Tabel -->
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($hariLibur as $index => $libur)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ \Carbon\Carbon::parse($libur->tanggal)->format('d-m-Y') }}</td>
                            <td>{{ $libur->keterangan }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center">Belum ada data hari libur untuk tahun {{ $year }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>

            </div>
        </div>
    </div>
</div>
@stop

@push('js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const btnTambah = document.getElementById('btnTambahForm');
        const wrapper = document.getElementById('libur-wrapper');

        btnTambah.addEventListener('click', function () {
            // Ambil form terakhir (jika ada)
            const lastItem = wrapper.querySelector('.libur-item:last-child');
            let lastTanggal = '';
            let lastKeterangan = '';

            if (lastItem) {
                lastTanggal = lastItem.querySelector('input[name="tanggal[]"]').value;
                lastKeterangan = lastItem.querySelector('input[name="keterangan[]"]').value;
            }

            // Buat form baru dengan nilai dari form terakhir
            const item = document.createElement('div');
            item.classList.add('libur-item', 'mb-3', 'border', 'p-2', 'rounded');
            item.innerHTML = `
                <label>Tanggal</label>
                <input type="date" name="tanggal[]" class="form-control mb-2" value="${lastTanggal}" required>
                <label>Keterangan</label>
                <input type="text" name="keterangan[]" class="form-control" value="${lastKeterangan}" placeholder="Contoh: Hari Nasional" required>
                <button type="button" class="btn btn-sm btn-danger mt-2 btn-hapus-form">Hapus</button>
            `;
            wrapper.appendChild(item);
        });

        // Hapus form dinamis
        wrapper.addEventListener('click', function (e) {
            if (e.target.classList.contains('btn-hapus-form')) {
                e.target.closest('.libur-item').remove();
            }
        });
    });
</script>
@endpush

