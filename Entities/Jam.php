<?php

namespace Modules\RekapKehadiran\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Jam extends Model
{
    use HasFactory;

    protected $table = 'jamkerja';
    protected $primaryKey = 'id';
    protected $fillable = ['nama', 'tanggal_mulai', 'tanggal_selesai', 'jenis', 'jam_masuk', 'jam_pulang', 'jam_kerja'];
}
