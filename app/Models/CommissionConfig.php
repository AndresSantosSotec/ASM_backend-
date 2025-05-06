<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionConfig extends Model
{
    use HasFactory;

    protected $table = 'commission_configs';

    protected $fillable = [
        'base_rate',           // porcentaje base (%) que aplica a todos
        'bonus_threshold',     // mínimo de conversiones para bonificación
        'bonus_rate',          // porcentaje adicional (%) cuando supera el umbral
        'period',              // 'monthly' o 'quarterly'
        'respect_personalized' // bool: si respeta o no las tasas individuales
    ];

    protected $casts = [
        'base_rate'           => 'decimal:2',
        'bonus_threshold'     => 'integer',
        'bonus_rate'          => 'decimal:2',
        'period'              => 'string',
        'respect_personalized' => 'boolean',
    ];

    /**
     * Todas las comisiones calculadas bajo este snapshot de configuración.
     */
    public function commissions()
    {
        return $this->hasMany(Commission::class, 'config_id');
    }
}
