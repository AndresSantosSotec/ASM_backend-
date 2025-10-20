<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdicionalEstudiante extends Model
{
    use HasFactory;

    protected $table = 'adicional_estudiantes';

    protected $fillable = [
        'carnet',
        'notas_pago',
        'nomenclatura',
    ];
}
