<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TareasGen extends Model
{
    use HasFactory;

    // Especifica el nombre correcto de la tabla
    protected $table = 'tareas';

    // Opcional: define los campos asignables de forma masiva
    protected $fillable = [
        'asesor_id',
        'creado_por',
        'titulo',
        'descripcion',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'tipo',
        'completada',
    ];
}
