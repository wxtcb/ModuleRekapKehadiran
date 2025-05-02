<?php

namespace Modules\RekapKehadiran\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KehadiranI extends Model
{
    use HasFactory;

    protected $table = 'presensi';
    protected $primaryKey = 'id';
    protected $fillable = [];
    
}
