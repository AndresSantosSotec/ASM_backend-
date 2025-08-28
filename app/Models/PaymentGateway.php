<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'commission_percentage',
        'api_key',
        'merchant_id',
        'active',
        'configuration',
    ];

    protected $casts = [
        'commission_percentage' => 'decimal:2',
        'active' => 'boolean',
        'configuration' => 'array',
    ];

    /**
     * Scope para pasarelas activas
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Verifica si la pasarela está completamente configurada
     */
    public function isFullyConfigured(): bool
    {
        return !empty($this->api_key) && !empty($this->merchant_id);
    }

    /**
     * Calcula la comisión para un monto dado
     */
    public function calculateCommission(float $amount): float
    {
        return $amount * ($this->commission_percentage / 100);
    }

    /**
     * Obtiene el monto total incluyendo comisión
     */
    public function getTotalAmountWithCommission(float $amount): float
    {
        return $amount + $this->calculateCommission($amount);
    }
}
