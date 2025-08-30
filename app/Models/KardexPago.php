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
        'numero_boleta',        // ← NUEVO
        'banco',                // ← NUEVO
        'archivo_comprobante',  // ← NUEVO
        'estado_pago',          // ← NUEVO
        'observaciones',
        'created_by',
        'updated_by'
    ];
    protected $casts = [
        'fecha_pago'  => 'date',
        'monto_pagado' => 'decimal:2',
    ];

    public function estudiantePrograma()
    {
        return $this->belongsTo(EstudiantePrograma::class, 'estudiante_programa_id');
    }

    public function cuota()
    {
        return $this->belongsTo(CuotaProgramaEstudiante::class, 'cuota_id');
    }
    /**
     * Scope para pagos pendientes de revisión
     */
    public function scopePendientesRevision($query)
    {
        return $query->where('estado_pago', 'pendiente_revision');
    }

    /**
     * Scope para pagos aprobados
     */
    public function scopeAprobados($query)
    {
        return $query->where('estado_pago', 'aprobado');
    }
}
