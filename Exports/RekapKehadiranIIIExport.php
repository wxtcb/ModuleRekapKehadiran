<?php

namespace  Modules\RekapKehadiran\Exports;

use App\Models\Pegawai;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RekapKehadiranIIIExport implements FromArray, WithHeadings, WithTitle
{
    protected $data;
    protected $year;

    public function __construct($data, $year)
    {
        $this->data = $data;
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
                $pegawai['total']['D'],
                $pegawai['total']['T'],
                $pegawai['total']['TM'],
                $pegawai['total']['C'],
                $pegawai['total']['DL']
            ];

            $rows[] = $row;
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'No',
            'NIP',
            'Nama',
            'Hadir (D)',
            'Hadir Tidak Lengkap (T)',
            'Tidak Absen (TM)',
            'Cuti (C)',
            'Dinas Luar (DL)'
        ];
    }

    public function title(): string
    {
        return 'Rekap Kehadiran Tahunan ' . $this->year;
    }
}
