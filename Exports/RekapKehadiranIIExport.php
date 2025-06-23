<?php

namespace  Modules\RekapKehadiran\Exports;

use App\Models\Pegawai;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RekapKehadiranIIExport implements FromArray, WithHeadings, WithTitle
{
    protected $data;
    protected $tanggalHari;
    protected $month;
    protected $year;

    public function __construct($data, $tanggalHari, $month, $year)
    {
        $this->data = $data;
        $this->tanggalHari = $tanggalHari;
        $this->month = $month;
        $this->year = $year;
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->data as $index => $pegawai) {
            $row = [
                $index + 1,
                $pegawai['nip'],
                $pegawai['nama'],
                $pegawai['keterangan'] ?? '', // Menambahkan keterangan
            ];

            // Menambahkan jam masuk dan jam pulang untuk setiap tanggal
            foreach ($pegawai['presensi'] as $idx => $presensi) {
                $jamMasuk = $pegawai['jam_masuk'][$idx] ?? '-';
                $jamPulang = $pegawai['jam_pulang'][$idx] ?? '-';
                
                // Gabungkan jam masuk dan jam pulang dalam satu cell dengan line break
                $jamKehadiran = $jamMasuk . "\n" . $jamPulang;
                
                $row[] = $jamKehadiran;
            }

            $row[] = $pegawai['total']['D'];
            $row[] = $pegawai['total']['TM'];
            $row[] = $pegawai['total']['C'];
            $row[] = $pegawai['total']['T'];
            $row[] = $pegawai['total']['DL'];

            $rows[] = $row;
        }

        return $rows;
    }

    public function headings(): array
    {
        $headings = ['No', 'NIP', 'Nama', 'Keterangan'];

        foreach ($this->tanggalHari as $tgl) {
            $headings[] = \Carbon\Carbon::parse($tgl)->format('d');
        }

        $headings[] = 'D';
        $headings[] = 'TM';
        $headings[] = 'C';
        $headings[] = 'T';
        $headings[] = 'DL';

        return $headings;
    }

    public function title(): string
    {
        return 'Rekap Kehadiran ' . $this->month . '-' . $this->year;
    }
}