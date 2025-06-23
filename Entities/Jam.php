<?php

namespace Modules\RekapKehadiran\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Jam extends Model
{
    use HasFactory;

    protected $table = 'jamkerja';
    protected $primaryKey = 'id';
    protected $fillable = ['nama', 'skema_absen', 'jam_istirahat_keluar', 'jam_istirahat_masuk', 'tanggal_mulai', 'tanggal_selesai', 'jenis', 'jam_masuk', 'jam_pulang', 'jam_kerja'];
}
