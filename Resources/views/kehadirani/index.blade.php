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
                    <h3 class="d-flex justify-content-between align-items-center">
                        Rekapitulasi Kehadiran Pegawai I

                        @if (!$isAdmin && $pegawaiId && auth()->user()->role_aktif != 'mahasiswa' && auth()->user()->role_aktif != 'kajur' )
                            <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#exportModal">
                                Unduh Excel Saya
                            </button>
                        @endif
                    </h3>

                    <!-- Export Modal -->
                    <div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="exportModalLabel">Pilih Format Export</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <p>Silakan pilih format file yang ingin diunduh:</p>
                                    <div class="d-flex justify-content-around">
                                        <a href="{{ route('rekap-harian.export', [
                                            'pegawai_id' => $pegawaiId,
                                            'month' => \Carbon\Carbon::parse($tanggal)->month,
                                            'year' => \Carbon\Carbon::parse($tanggal)->year,
                                            'format' => 'excel'
                                        ]) }}" class="btn btn-success">
                                            <i class="fas fa-file-excel"></i> Excel
                                        </a>
                                        <a href="{{ route('rekap-harian.export', [
                                            'pegawai_id' => $pegawaiId,
                                            'month' => \Carbon\Carbon::parse($tanggal)->month,
                                            'year' => \Carbon\Carbon::parse($tanggal)->year,
                                            'format' => 'pdf'
                                        ]) }}" class="btn btn-danger">
                                            <i class="fas fa-file-pdf"></i> PDF
                                        </a>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form method="GET" class="row mb-3">
                        {{-- Form Pencarian Nama --}}
                        <div class="col-md-5">
                            <label for="nama">Cari Nama Pegawai:</label>
                            <input type="text" name="nama" id="nama" class="form-control"
                                placeholder="Masukkan nama pegawai..." value="{{ request('nama') }}"
                                oninput="this.form.submit()">
                        </div>
                        {{-- Form Pilih Tanggal --}}
                        <div class="col-md-3">
                            <label for="tanggal">Pilih Tanggal:</label>
                            <input type="date" name="tanggal" id="tanggal" class="form-control"
                                max="{{ date('Y-m-d') }}" value="{{ request('tanggal', date('Y-m-d')) }}"
                                onchange="this.form.submit()">
                        </div>
                    </form>

                    <div class="mt-2">
                        @include('layouts.partials.messages')
                    </div>

                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>NIP</th>
                                <th>Nama</th>
                                <th>Keterangan</th>
                                <th>Jam Masuk</th>
                                <th>Jam Pulang</th>
                                <th>Status</th>
                                <th>Waktu Kerja</th>
                                @if (in_array(auth()->user()->role_aktif, ['admin', 'kajur']))
                                    <th>Download</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rekapPresensi as $index => $data)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $data->nip }}</td>
                                    <td>{{ $data->nama }}</td>
                                    <td>{{ $data->keterangan }}</td>
                                    <td>{{ $data->waktu_datang }}</td>
                                    <td>{{ $data->waktu_pulang }}</td>
                                    <td>{{ $data->status }}</td>
                                    <td>{{ $data->durasi_jam }}</td>
                                    @if (in_array(auth()->user()->role_aktif, ['admin', 'kajur']))
                                        <td>
                                            <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#exportModal{{ $index }}">
                                                Pilih Format
                                            </button>
                                            
                                            <!-- Modal for each row -->
                                            <div class="modal fade" id="exportModal{{ $index }}" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel{{ $index }}" aria-hidden="true">
                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="exportModalLabel{{ $index }}">Export Data {{ $data->nama }}</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Pilih format untuk mengekspor data:</p>
                                                            <div class="d-flex justify-content-around">
                                                                <a href="{{ route('rekap-harian.export', [
                                                                    'pegawai_id' => \Modules\Pengaturan\Entities\Pegawai::where('nip', $data->nip)->value('id'),
                                                                    'month' => \Carbon\Carbon::parse(request('tanggal', now()))->month,
                                                                    'year' => \Carbon\Carbon::parse(request('tanggal', now()))->year,
                                                                    'format' => 'excel'
                                                                ]) }}" class="btn btn-success">
                                                                    <i class="fas fa-file-excel"></i> Excel
                                                                </a>
                                                                <a href="{{ route('rekap-harian.export', [
                                                                    'pegawai_id' => \Modules\Pengaturan\Entities\Pegawai::where('nip', $data->nip)->value('id'),
                                                                    'month' => \Carbon\Carbon::parse(request('tanggal', now()))->month,
                                                                    'year' => \Carbon\Carbon::parse(request('tanggal', now()))->year,
                                                                    'format' => 'pdf'
                                                                ]) }}" class="btn btn-danger">
                                                                    <i class="fas fa-file-pdf"></i> PDF
                                                                </a>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop

@section('adminlte_js')
<script>
    // JavaScript untuk menangani modal jika diperlukan
    $(document).ready(function() {
        // Inisialisasi modal jika ada
        $('.modal').on('show.bs.modal', function () {
            // Tambahkan logika tambahan jika diperlukan
        });
    });
</script>
@stop