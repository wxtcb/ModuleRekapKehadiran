<?php

namespace Modules\RekapKehadiran\Database\Seeders;

use App\Models\Core\Menu;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class MenuModulRekapkehadiranTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        Menu::where('modul', 'RekapKehadiran')->delete();
        $parent = Menu::create([
            'modul' => 'RekapKehadiran',
            'label' => 'Rekap Kehadiran',
            'url' => 'rekapkehadiran',
            'can' => serialize(['admin', 'terdaftar', 'mahasiswa', 'pegawai', 'dosen', 'kajur', 'direktur']),
            'icon' => 'fas fa-copy',
            'urut' => 1,
            'parent_id' => 0,
            'active' => '',
        ]);
        Menu::create([
            'modul' => 'RekapKehadiran',
            'label' => 'Harian',
            'url' => 'rekapkehadiran/kehadirani',
            'can' => serialize(['admin', 'terdaftar', 'mahasiswa', 'pegawai', 'dosen', 'kajur', 'direktur']),
            'icon' => 'far fa-circle',
            'urut' => 1,
            'parent_id' => $parent->id,
            'active' => serialize(['rekapkehadiran/kehadirani']),
        ]);
        Menu::create([
            'modul' => 'RekapKehadiran',
            'label' => 'Bulanan',
            'url' => 'rekapkehadiran/kehadiranii',
            'can' => serialize(['admin', 'terdaftar', 'pegawai', 'dosen', 'kajur', 'direktur']),
            'icon' => 'far fa-circle',
            'urut' => 2,
            'parent_id' => $parent->id,
            'active' => serialize(['rekapkehadiran/kehadiranii', 'pegawai', 'dosen']),
        ]);
        Menu::create([
            'modul' => 'RekapKehadiran',
            'label' => 'Tahunan',
            'url' => 'rekapkehadiran/kehadiraniii',
            'can' => serialize(['admin', 'terdaftar', 'pegawai', 'dosen', 'kajur', 'direktur']),
            'icon' => 'far fa-circle',
            'urut' => 3,
            'parent_id' => $parent->id,
            'active' => serialize(['rekapkehadiran/kehadiraniii']),
        ]);
    }
}
