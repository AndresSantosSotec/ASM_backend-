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
        // columnas nuevas
        'numero_boleta_normalizada',
        'banco_normalizado',
        'boleta_fingerprint',
        'archivo_hash',
    ];

    protected $casts = [
        'fecha_pago'   => 'datetime',
        'monto_pagado' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::saving(function (self $model) {
            // Normalización defensiva
            if (!empty($model->numero_boleta)) {
                $norm = preg_replace('/[^A-Z0-9]/u', '', mb_strtoupper($model->numero_boleta, 'UTF-8'));
                $model->numero_boleta_normalizada = $norm;
            }

            if (!empty($model->banco)) {
                $b = mb_strtoupper(trim($model->banco), 'UTF-8');
                $map = [
                    'BANCO INDUSTRIAL' => ['BI','BANCO INDUSTRIAL','INDUSTRIAL'],
                    'BANRURAL'         => ['BANRURAL','BAN RURAL','RURAL'],
                    'BAM'              => ['BAM','BANCO AGROMERCANTIL'],
                    'G&T CONTINENTAL'  => ['G&T','G Y T','GYT','G&T CONTINENTAL'],
                    'PROMERICA'        => ['PROMERICA'],
                ];
                $canon = $b;
                foreach ($map as $c => $aliases) {
                    if (in_array($b, $aliases, true)) {
                        $canon = $c;
                        break;
                    }
                }
                $model->banco_normalizado = $canon;
            }

            if (!empty($model->banco_normalizado) && !empty($model->numero_boleta_normalizada)) {
                $model->boleta_fingerprint = hash('sha256', $model->banco_normalizado.'|'.$model->numero_boleta_normalizada);
            }

            // Nota: archivo_hash se setea en el controller con hash_file() ANTES de mover el archivo.
            // Aquí solo validamos que exista si hay archivo.
            if (!empty($model->archivo_comprobante) && empty($model->archivo_hash)) {
                // no recalculamos aquí porque ya movimos el archivo; se calcula en el controller.
            }
        });
    }

    public function estudiantePrograma()
    {
        return $this->belongsTo(EstudiantePrograma::class, 'estudiante_programa_id');
    }

    public function cuota()
    {
        return $this->belongsTo(CuotaProgramaEstudiante::class, 'cuota_id');
    }

    public function scopePendientesRevision($q)
    {
        return $q->where('estado_pago', 'pendiente_revision');
    }

    public function scopeAprobados($q)
    {
        return $q->where('estado_pago', 'aprobado');
    }
}
