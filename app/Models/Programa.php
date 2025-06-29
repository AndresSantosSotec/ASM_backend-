<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Programa extends Model
{
    use HasFactory;

    protected $table = 'tb_programas';
    public $timestamps = false;

    protected $fillable = [
        'abreviatura','nombre_del_programa','meses',
        'area_comun','cursos_de_bba','area_de_especialidad',
        'seminario_de_gerencia','capstone_project',
        'escritura_de_casos','certificacion_internacional',
        'total_cursos','fecha_creacion','activo',
    ];

    public function precios()
    {
        return $this->hasOne(PrecioPrograma::class,'programa_id');
    }

    // relación N-N con Course
    public function courses()
    {
        return $this->belongsToMany(
            Course::class,
            'programa_course',
            'programa_id',
            'course_id'
        )->withTimestamps();
    }
}
