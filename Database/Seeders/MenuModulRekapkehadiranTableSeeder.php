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
        $menu = Menu::create([
            'modul' => 'RekapKehadiran',
            'label' => 'Rekapitulasi',
            'url' => 'rekapkehadiran',
            'can' => serialize(['admin']),
            'icon' => 'fas fa-copy',
            'urut' => 1,
            'parent_id' => 0,
            'active' => serialize(['RekapKehadiran']),
        ]);
        if ($menu) {
            Menu::create([
                'modul' => 'RekapKehadiran',
                'label' => 'Kehadiran Pegawai I',
                'url' => 'rekapkehadiran/pegawai1',
                'can' => serialize(['admin']),
                'icon' => 'far fa-circle',
                'urut' => 1,
                'parent_id' => $menu->id,
                'active' => serialize(['rekapkehadiran/pegawai1', 'rekapkehadiran/pegawai1*']),
            ]);
        }
        if ($menu) {
            Menu::create([
                'modul' => 'RekapKehadiran',
                'label' => 'Kehadiran Pegawai II',
                'url' => 'rekapkehadiran/pegawai2',
                'can' => serialize(['admin']),
                'icon' => 'far fa-circle',
                'urut' => 1,
                'parent_id' => $menu->id,
                'active' => serialize(['rekapkehadiran/pegawai2', 'rekapkehadiran/pegawai2*']),
            ]);
        }
        if ($menu) {
            Menu::create([
                'modul' => 'RekapKehadiran',
                'label' => 'Kehadiran Pegawai III',
                'url' => 'rekapkehadiran/pegawai3',
                'can' => serialize(['admin']),
                'icon' => 'far fa-circle',
                'urut' => 1,
                'parent_id' => $menu->id,
                'active' => serialize(['rekapkehadiran/pegawai3', 'rekapkehadiran/pegawai3*']),
            ]);
        }
    }
}
