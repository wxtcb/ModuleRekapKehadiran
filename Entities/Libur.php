<?php

namespace Modules\RekapKehadiran\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Libur extends Model
{
    use HasFactory;

    protected $table = 'harilibur';
    protected $primaryKey = 'id';
    protected $fillable = ['tanggal', 'keterangan'];
}
