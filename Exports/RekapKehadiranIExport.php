<?php

namespace Modules\RekapKehadiran\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\Cuti\Entities\Cuti;
use Modules\Pengaturan\Entities\Pegawai;
use Modules\RekapKehadiran\Entities\KehadiranI;
use Modules\Setting\Entities\Libur;
use App\Models\JamKerja; // pastikan namespace ini benar sesuai struktur Anda
use Modules\Setting\Entities\Jam;

class RekapKehadiranIExport implements FromArray, WithHeadings, WithTitle
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

    public function array(): array
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

            // Default status
            $status = 'Alpha';

            $isLibur = $tanggal->isWeekend() || in_array($tanggalStr, $liburTanggal);
            $isCuti = Cuti::where('pegawai_id', $pegawai->id)
                ->where('status', 'Selesai')
                ->whereDate('tanggal_mulai', '<=', $tanggalStr)
                ->whereDate('tanggal_selesai', '>=', $tanggalStr)
                ->exists();

            // Default minimal jam kerja
            $jenis = method_exists($pegawai, 'getJenis') ? $pegawai->getJenis() : (str_contains(strtolower($pegawai->nama), 'dosen') ? 'dosen' : 'pegawai');
            $minimalJamKerja = $jenis === 'dosen' ? 4 : 8;

            // Cek apakah ada pengaturan jam kerja khusus
            $jamKerjaCustom = Jam::whereDate('tanggal', $tanggalStr)
                ->where('jenis', $jenis)
                ->first();

            if ($jamKerjaCustom && $jamKerjaCustom->jam_kerja) {
                $minimalJamKerja = $jamKerjaCustom->jam_kerja;
            }

            // Cek durasi kerja
            $durasi = '-';
            $jam = 0;
            if ($datang && $pulang) {
                $start = Carbon::parse($datang->checktime);
                $end = Carbon::parse($pulang->checktime);
                $diffInMinutes = $end->diffInMinutes($start);
                $jam = floor($diffInMinutes / 60);
                $menit = $diffInMinutes % 60;
                $durasi = "{$jam} jam {$menit} menit";
            }

            // Tentukan status akhir
            if ($isLibur) {
                $status = 'Libur';
            } elseif ($isCuti) {
                $status = 'Cuti';
            } elseif ($datang && $pulang) {
                if ($jam >= $minimalJamKerja) {
                    $status = 'Hadir';
                } else {
                    $status = 'Hadir (Tidak Mendapat Tunjangan)';
                }
            } elseif ($datang || $pulang) {
                $status = 'Hadir (Lupa presensi)';
            }

            $data[] = [
                $i === 1 ? $pegawai->nama : '',
                $i === 1 ? $pegawai->nip : '',
                $tanggal->format('d-m-Y'),
                $waktuDatang,
                $waktuPulang,
                $status,
                $durasi,
            ];
        }

        return $data;
    }

    public function headings(): array
    {
        return ['Nama', 'NIP', 'Tanggal', 'Jam Masuk', 'Jam Pulang', 'Status', 'Durasi Kerja'];
    }

    public function title(): string
    {
        return 'Rekap Kehadiran';
    }
}
