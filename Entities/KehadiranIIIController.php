<?php

namespace Modules\RekapKehadiran\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KehadiranIIIController extends Model
{
    use HasFactory;

    protected $fillable = [];
    
    protected static function newFactory()
    {
        return \Modules\RekapKehadiran\Database\factories\KehadiranIIIControllerFactory::new();
    }
}
