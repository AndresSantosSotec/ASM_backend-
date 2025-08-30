<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
//use Illuminate\Database\Eloquent\SoftDeletes;

class CuotaProgramaEstudiante extends Model
{
    //use SoftDeletes;

    protected $table = 'cuotas_programa_estudiante';
    protected $fillable = [
      'estudiante_programa_id',
      'numero_cuota',
      'fecha_vencimiento',
      'monto',
      'estado',
      'paid_at',
      'created_by','updated_by','deleted_by'
    ];
    protected $casts = [
      'fecha_vencimiento' => 'date',
      'paid_at'           => 'datetime',
      'monto'             => 'decimal:2',
    ];

    public function estudiantePrograma()
    {
        return $this->belongsTo(EstudiantePrograma::class, 'estudiante_programa_id');
    }

    public function pagos()
    {
        // Si quieres enlazar pagos a una cuota específica
        return $this->hasMany(KardexPago::class, 'cuota_id');
    }
}
