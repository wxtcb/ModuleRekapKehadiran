<?php

namespace Modules\RekapKehadiran\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Pengaturan\Entities\Pegawai;

class KehadiranI extends Model
{
    use HasFactory;
    protected $connection = 'second_db';
    protected $table = 'presensi';
    protected $primaryKey = 'id';
    protected $fillable = [];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id', 'id');
    }
}
