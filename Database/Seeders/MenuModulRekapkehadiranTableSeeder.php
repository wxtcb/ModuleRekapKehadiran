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
            'label' => 'Rekapitulasi',
            'url' => 'rekapkehadiran',
            'can' => serialize(['admin', 'terdaftar']),
            'icon' => 'fas fa-copy',
            'urut' => 1,
            'parent_id' => 0,
            'active' => '',
        ]);
        Menu::create([
            'modul' => 'RekapKehadiran',
            'label' => 'Kehadiran Pegawai I',
            'url' => 'rekapkehadiran/kehadirani',
            'can' => serialize(['admin', 'terdaftar']),
            'icon' => 'far fa-circle',
            'urut' => 1,
            'parent_id' => $parent->id,
            'active' => serialize(['rekapkehadiran/kehadirani']),
        ]);
        Menu::create([
            'modul' => 'RekapKehadiran',
            'label' => 'Kehadiran Pegawai II',
            'url' => 'rekapkehadiran/kehadiranii',
            'can' => serialize(['admin', 'terdaftar']),
            'icon' => 'far fa-circle',
            'urut' => 2,
            'parent_id' => $parent->id,
            'active' => serialize(['rekapkehadiran/kehadiranii']),
        ]);
        Menu::create([
            'modul' => 'RekapKehadiran',
            'label' => 'Kehadiran Pegawai III',
            'url' => 'rekapkehadiran/kehadiraniii',
            'can' => serialize(['admin', 'terdaftar']),
            'icon' => 'far fa-circle',
            'urut' => 3,
            'parent_id' => $parent->id,
            'active' => serialize(['rekapkehadiran/kehadiraniii']),
        ]);
    }
}
