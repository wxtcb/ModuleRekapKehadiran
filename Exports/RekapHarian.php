<?php

namespace Modules\RekapKehadiran\Exports;

use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Modules\Cuti\Entities\Cuti;
use Modules\Pengaturan\Entities\Pegawai;
use Modules\RekapKehadiran\Entities\Jam;
use Modules\RekapKehadiran\Entities\KehadiranI;
use Modules\RekapKehadiran\Entities\Libur;
use Modules\SuratIjin\Entities\LupaAbsen;
use Modules\SuratIjin\Entities\Terlambat;
use Modules\SuratTugas\Entities\AnggotaSuratTugas;
use Modules\SuratTugas\Entities\DetailSuratTugas;

class RekapKehadiranIPdfExport implements FromView
{
    protected $pegawaiId;
    protected $month;
    protected $year;

    public function __construct($pegawaiId, $month, $year)
    {
        $this->pegawaiId = $pegawaiId;
        $this->month = $month;
        $this->year = $year;
    }

    public function view(): View
    {
        $pegawai = Pegawai::findOrFail($this->pegawaiId);
        $totalHari = Carbon::create($this->year, $this->month)->daysInMonth;

        $liburTanggal = Libur::whereMonth('tanggal', $this->month)
            ->whereYear('tanggal', $this->year)
            ->pluck('tanggal')
            ->map(fn($tgl) => Carbon::parse($tgl)->format('Y-m-d'))
            ->toArray();

        $data = [];

        for ($i = 1; $i <= $totalHari; $i++) {
            $tanggal = Carbon::create($this->year, $this->month, $i);
            $tanggalStr = $tanggal->format('Y-m-d');

            $checkins = KehadiranI::on('second_db')
                ->whereDate('checktime', $tanggalStr)
                ->where('user_id', $pegawai->id)
                ->get();

            $datang = $checkins->where('checktype', 'I')->sortBy('checktime')->first();
            $pulang = $checkins->where('checktype', 'O')->sortByDesc('checktime')->first();

            $waktuDatang = $datang ? date('H:i:s', strtotime($datang->checktime)) : '';
            $waktuPulang = $pulang ? date('H:i:s', strtotime($pulang->checktime)) : '';

            $status = 'Alpha';

            $isLibur = $tanggal->isWeekend() || in_array($tanggalStr, $liburTanggal);
            $isCuti = Cuti::where('pegawai_id', $pegawai->id)
                ->where('status', 'Selesai')
                ->whereDate('tanggal_mulai', '<=', $tanggalStr)
                ->whereDate('tanggal_selesai', '>=', $tanggalStr)
                ->exists();

            $isDinasLuarDetail = DetailSuratTugas::where('pegawai_id', $pegawai->id)
                ->whereDate('tanggal_mulai', '<=', $tanggalStr)
                ->whereDate('tanggal_selesai', '>=', $tanggalStr)
                ->exists();

            $isDinasLuarAnggota = AnggotaSuratTugas::where('pegawai_id', $pegawai->id)
                ->whereHas('suratTugas.detail', function ($query) use ($tanggalStr) {
                    $query->whereDate('tanggal_mulai', '<=', $tanggalStr)
                        ->whereDate('tanggal_selesai', '>=', $tanggalStr);
                })
                ->exists();

            $isDinasLuar = $isDinasLuarDetail || $isDinasLuarAnggota;

            $roles = optional($pegawai->user)->roles->pluck('name')->toArray();
            $jenis = in_array('dosen', $roles) ? 'dosen' : 'pegawai';
            $minimalJamKerja = $jenis === 'dosen' ? 4 : 8;

            $jamKerjaCustom = Jam::where('jenis', $jenis)
                ->whereDate('tanggal_mulai', '<=', $tanggalStr)
                ->whereDate('tanggal_selesai', '>=', $tanggalStr)
                ->first();

            if ($jamKerjaCustom && !empty($jamKerjaCustom->jam_kerja)) {
                $jamKerjaStr = strtolower(trim($jamKerjaCustom->jam_kerja));
                $jamKerjaStr = preg_replace('/\s+/', ' ', $jamKerjaStr);
                if (preg_match('/(\d+)\s*jam\s*(\d+)\s*menit/', $jamKerjaStr, $matches)) {
                    $jamMinimal = (int)$matches[1];
                    $menitMinimal = (int)$matches[2];
                    $minimalJamKerja = $jamMinimal + ($menitMinimal / 60);
                }
            }

            $durasi = '-';
            $jam = 0;
            $menit = 0;
            $kurang_dari_jam_kerja = false;

            if ($datang && $pulang) {
                $start = Carbon::parse($datang->checktime);
                $end = Carbon::parse($pulang->checktime);
                $diffInMinutes = $end->diffInMinutes($start);
                $jam = floor($diffInMinutes / 60);
                $menit = $diffInMinutes % 60;
                $durasi = "{$jam} jam {$menit} menit";
                if (($jam + ($menit / 60)) < $minimalJamKerja) {
                    $kurang_dari_jam_kerja = true;
                }
            }

            $izinMasukDisetujui =
                Terlambat::where('pegawai_id', $pegawai->id)
                    ->where('status', 'Disetujui')
                    ->whereDate('tanggal', $tanggalStr)
                    ->whereIn('jenis_ijin', ['Terlambat'])
                    ->exists() ||

                LupaAbsen::where('pegawai_id', $pegawai->id)
                    ->where('status', 'Disetujui')
                    ->whereDate('tanggal', $tanggalStr)
                    ->whereIn('jenis_ijin', ['Lupa Absen Masuk'])
                    ->exists();

            $izinPulangDisetujui =
                Terlambat::where('pegawai_id', $pegawai->id)
                    ->where('status', 'Disetujui')
                    ->whereDate('tanggal', $tanggalStr)
                    ->whereIn('jenis_ijin', ['Pulang Cepat'])
                    ->exists() ||

                LupaAbsen::where('pegawai_id', $pegawai->id)
                    ->where('status', 'Disetujui')
                    ->whereDate('tanggal', $tanggalStr)
                    ->whereIn('jenis_ijin', ['Lupa Absen Pulang'])
                    ->exists();

            if ($isLibur) {
                $status = 'Libur';
            } elseif ($isCuti) {
                $status = 'Cuti';
            } elseif ($isDinasLuar) {
                $status = 'Dinas Luar';
            } elseif (!$datang && !$pulang && !$izinMasukDisetujui && !$izinPulangDisetujui) {
                $status = 'Alpha';
            } elseif (!$datang && !$izinMasukDisetujui) {
                $status = 'Hadir (Lupa presensi datang)';
            } elseif (!$pulang && !$izinPulangDisetujui) {
                $status = 'Hadir (Lupa presensi pulang)';
            } elseif ($kurang_dari_jam_kerja) {
                if (!$izinMasukDisetujui || !$izinPulangDisetujui) {
                    $status = 'Hadir';
                } else {
                    $status = 'Hadir (Tidak Mendapat Tunjangan)';
                }
            } else {
                $status = 'Hadir';
            }

            $data[] = [
                'nama' => $i === 1 ? $pegawai->nama : '',
                'nip' => $i === 1 ? $pegawai->nip : '',
                'tanggal' => $tanggal->format('d-m-Y'),
                'waktu_datang' => $waktuDatang,
                'waktu_pulang' => $waktuPulang,
                'status' => $status,
                'durasi' => $durasi,
            ];
        }

        $monthName = Carbon::create($this->year, $this->month)->translatedFormat('F Y');
        
        return view('rekapkehadiran::pdf.kehadiran_i', [
            'data' => $data,
            'pegawai' => $pegawai,
            'monthName' => $monthName,
            'headings' => ['Nama', 'NIP', 'Tanggal', 'Jam Masuk', 'Jam Pulang', 'Status', 'Durasi Kerja']
        ]);
    }
}