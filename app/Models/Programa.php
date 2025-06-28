<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Programa extends Model
{
    use HasFactory;

    // Especifica la tabla asociada al modelo
    protected $table = 'tb_programas';

    // Si no usas timestamps (created_at, updated_at), desactívalos
    public $timestamps = false;

    // Define los campos que se pueden llenar masivamente
    protected $fillable = [
        'abreviatura',
        'nombre_del_programa',
        'meses',
        'area_comun',
        'cursos_de_bba',
        'area_de_especialidad',
        'seminario_de_gerencia',
        'capstone_project',
        'escritura_de_casos',
        'certificacion_internacional',
        'total_cursos',
        'fecha_creacion',
        'activo',
    ];

    public function precios()
    {
        return $this->hasOne(PrecioPrograma::class, 'programa_id');
    }

    public function cursos()
    {
        return $this->hasMany(Course::class, 'carrera');
    }

}
