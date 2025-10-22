<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProspectoAdicional extends Model
{
    protected $table = 'prospectos_adicionales';

    protected $fillable = [
        'id_estudiante',
        'id_estudiante_programa',
        'notas_pago',
        'nomenclatura',
        'status_actual',
    ];

    public function estudiante()
    {
        return $this->belongsTo(Prospecto::class, 'id_estudiante');
    }

    public function estudiantePrograma()
    {
        return $this->belongsTo(EstudiantePrograma::class, 'id_estudiante_programa');
    }
}
