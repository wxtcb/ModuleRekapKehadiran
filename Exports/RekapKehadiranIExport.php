<?php

namespace  Modules\RekapKehadiran\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\Cuti\Entities\Cuti;
use Modules\Pengaturan\Entities\Pegawai;
use Modules\RekapKehadiran\Entities\KehadiranI;
use Modules\Setting\Entities\Libur;

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
            ->map(fn ($tgl) => Carbon::parse($tgl)->format('Y-m-d'))
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
            $pulang = $checkins->where('checktype', 'O')->sortBy('checktime')->last();

            $waktuDatang = $datang ? date('H:i:s', strtotime($datang->checktime)) : '';
            $waktuPulang = $pulang ? date('H:i:s', strtotime($pulang->checktime)) : '';

            // Cek status presensi
            $status = 'Alpha';

            $isLibur = $tanggal->isWeekend() || in_array($tanggalStr, $liburTanggal);
            $isCuti = Cuti::where('pegawai_id', $pegawai->id)
                ->where('status', 'Selesai')
                ->whereDate('tanggal_mulai', '<=', $tanggalStr)
                ->whereDate('tanggal_selesai', '>=', $tanggalStr)
                ->exists();

            if ($isLibur) {
                $status = 'Libur';
            } elseif ($isCuti) {
                $status = 'Cuti';
            } elseif ($datang && $pulang) {
                $status = 'Hadir';
            } elseif ($datang || $pulang) {
                $status = 'Hadir (Lupa presensi)';
            }

            $durasi = '-';
            if ($datang && $pulang) {
                $diff = strtotime($pulang->checktime) - strtotime($datang->checktime);
                $jam = floor($diff / 3600);
                $menit = floor(($diff % 3600) / 60);
                $durasi = "{$jam} jam {$menit} menit";
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
