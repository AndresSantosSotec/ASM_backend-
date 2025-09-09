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
        'numero_boleta',        
        'banco',                
        'archivo_comprobante',  
        'estado_pago',          
        'observaciones',
        'banco_norm',
        'numero_boleta_norm',
        'file_sha256',
        'fecha_aprobacion',
        'aprobado_por',
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

    /**
     * Normalize bank name for uniqueness checks
     */
    public static function normalizeBanco($banco)
    {
        return strtoupper(trim($banco));
    }

    /**
     * Normalize receipt number for uniqueness checks
     */
    public static function normalizeNumeroBoleta($numeroBoleta)
    {
        // Remove spaces, dashes and keep only A-Z0-9
        $cleaned = preg_replace('/[^A-Z0-9]/', '', strtoupper($numeroBoleta));
        return $cleaned;
    }

    /**
     * Calculate SHA-256 hash of a file
     */
    public static function calculateFileHash($filePath)
    {
        return hash_file('sha256', $filePath);
    }

    /**
     * Check if a bank receipt combination already exists
     */
    public static function receiptExists($banco, $numeroBoleta)
    {
        $bancoNorm = self::normalizeBanco($banco);
        $boletaNorm = self::normalizeNumeroBoleta($numeroBoleta);
        
        return self::where('banco_norm', $bancoNorm)
                   ->where('numero_boleta_norm', $boletaNorm)
                   ->first();
    }

    /**
     * Check if a file hash already exists for a student
     */
    public static function fileHashExists($estudianteProgramaId, $fileHash)
    {
        return self::where('estudiante_programa_id', $estudianteProgramaId)
                   ->where('file_sha256', $fileHash)
                   ->first();
    }
}
