<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InscripcionPeriodo extends Model
{
    use HasFactory;

    protected $table = 'inscripciones_periodo';
    public    $timestamps = false;

    protected $fillable = [
        'periodo_id',
        'estudiante_id',
        'fecha_inscripcion',
        'estado',
    ];

    protected $casts = [
        'fecha_inscripcion' => 'datetime',
    ];

    public function periodo()
    {
        return $this->belongsTo(
            PeriodoInscripcion::class,
            'periodo_id'
        );
    }

    // Si tienes un modelo Estudiante, añade:
    // public function estudiante()
    // {
    //     return $this->belongsTo(Estudiante::class, 'estudiante_id');
    // }
}
