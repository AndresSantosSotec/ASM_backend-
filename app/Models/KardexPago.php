<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KardexPago extends Model
{
    protected $table = 'kardex_pagos';
    protected $fillable = [
      'estudiante_programa_id',
      'cuota_id',
      'fecha_pago',
      'monto_pagado',
      'metodo_pago',
      'observaciones',
      'created_by','updated_by'
    ];
    protected $casts = [
      'fecha_pago'  => 'date',
      'monto_pagado'=> 'decimal:2',
    ];

    public function estudiantePrograma()
    {
        return $this->belongsTo(EstudiantePrograma::class, 'estudiante_programa_id');
    }

    public function cuota()
    {
        return $this->belongsTo(CuotaProgramaEstudiante::class, 'cuota_id');
    }
}
