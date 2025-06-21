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
                <h3>Setting Jam Kerja Pegawai</h3>
                <div class="lead">

                </div>

                <div class="mt-2">
                    @include('layouts.partials.messages')
                </div>

                <!-- Tombol trigger modal -->
                <button type="button" class="btn btn-primary mb-3" data-toggle="modal" data-target="#jamKerjaModal">
                    Tambah Jam Kerja
                </button>


                <!-- Modal -->
                <div class="modal fade" id="jamKerjaModal" tabindex="-1" aria-labelledby="jamKerjaModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <form method="POST" action="{{ route('jam.store') }}">
                            @csrf
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="jamKerjaModalLabel">Tambah Jam Kerja</h5>
                                    <!-- Modal Header Close Button -->
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>

                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label>Nama Jam Kerja</label>
                                        <input type="text" name="nama" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>Jenis Karyawan</label>
                                        <select name="jenis" class="form-control" id="jenis-karyawan" required>
                                            <option value="">-- Pilih --</option>
                                            <option value="pegawai">Pegawai</option>
                                            <option value="dosen">Dosen</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label>Tanggal Mulai</label>
                                        <input type="date" name="tanggal_mulai" class="form-control">
                                    </div>
                                    <div class="mb-3">
                                        <label>Tanggal Selesai</label>
                                        <input type="date" name="tanggal_selesai" class="form-control">
                                    </div>
                                    <div class="mb-3 pegawai-only" style="display: none;">
                                        <label>Jam Masuk</label>
                                        <input type="time" name="jam_masuk" class="form-control">
                                    </div>
                                    <div class="mb-3 pegawai-only" style="display: none;">
                                        <label>Jam Pulang</label>
                                        <input type="time" name="jam_pulang" class="form-control">
                                    </div>
                                    <div class="mb-3 dosen-only" style="display: none;">
                                        <label>Jam Kerja</label>
                                        <input type="text" name="jam_kerja" placeholder="Contoh: 3 jam 0 menit" class="form-control">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-primary">Simpan</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Nama Jam Kerja</th>
                            <th>Tanggal Mulai</th>
                            <th>Tanggal Selesai</th>
                            <th>Jenis Karyawan</th>
                            <th>Jam Masuk</th>
                            <th>Jam Pulang</th>
                            <th>Jam Kerja</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jamKerjas as $item)
                        <tr>
                            <td>{{ $item->nama }}</td>
                            <td>{{ $item->tanggal_mulai }}</td>
                            <td>{{ $item->tanggal_selesai }}</td>
                            <td>{{ ucfirst($item->jenis) }}</td>
                            <td>{{ $item->jam_masuk ?? '-' }}</td>
                            <td>{{ $item->jam_pulang ?? '-' }}</td>
                            <td>{{ $item->jam_kerja }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">Belum ada data jam kerja.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>

            </div>
        </div>
    </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const jenisSelect = document.getElementById('jenis-karyawan');
        jenisSelect.addEventListener('change', function() {
            document.querySelectorAll('.pegawai-only, .dosen-only').forEach(el => el.style.display = 'none');
            if (this.value === 'pegawai') {
                document.querySelectorAll('.pegawai-only').forEach(el => el.style.display = 'block');
            } else if (this.value === 'dosen') {
                document.querySelectorAll('.dosen-only').forEach(el => el.style.display = 'block');
            }
        });
    });
</script>
@stop