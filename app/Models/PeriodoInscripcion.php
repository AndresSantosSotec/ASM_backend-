<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Programa;
use App\Models\InscripcionPeriodo;

class PeriodoInscripcion extends Model
{
    use HasFactory;

    protected $table = 'tb_periodos_inscripcion';
    public    $timestamps = false;

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'nombre',
        'codigo',
        'fecha_inicio',
        'fecha_fin',
        'descripcion',
        'cupos_total',
        'descuento',
        'activo',
        'visible',
        'notificaciones',
        'inscritos_count',
    ];

    protected $casts = [
        'fecha_inicio'    => 'date',
        'fecha_fin'       => 'date',
        'activo'          => 'boolean',
        'visible'         => 'boolean',
        'notificaciones'  => 'boolean',
        'inscritos_count' => 'integer',
    ];

    /**
     * Relación many-to-many con tb_programas
     */
    public function programas()
    {
        return $this->belongsToMany(
            Programa::class,
            'tb_periodo_programa',
            'periodo_id',
            'programa_id'
        );
    }

    /**
     * Relación one-to-many con inscripciones_periodo
     */
    public function inscripciones()
    {
        return $this->hasMany(
            InscripcionPeriodo::class,
            'periodo_id'
        );
    }
}
