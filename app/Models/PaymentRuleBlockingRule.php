<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRuleBlockingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_rule_id',
        'name',
        'description',
        'days_after_due',
        'affected_services',
        'active',
    ];

    protected $casts = [
        'affected_services' => 'array',
        'active' => 'boolean',
        'days_after_due' => 'integer',
    ];

    /**
     * Relación con PaymentRule
     */
    public function paymentRule(): BelongsTo
    {
        return $this->belongsTo(PaymentRule::class);
    }

    /**
     * Scope para reglas activas
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope para ordenar por días después de vencimiento
     */
    public function scopeOrderByDays($query)
    {
        return $query->orderBy('days_after_due');
    }

    /**
     * Verifica si un servicio está afectado por esta regla
     */
    public function affectsService(string $service): bool
    {
        return in_array($service, $this->affected_services ?? []);
    }

    /**
     * Obtiene los nombres legibles de los servicios afectados
     */
    public function getReadableServicesAttribute(): array
    {
        $serviceNames = [
            'plataforma' => 'Plataforma',
            'evaluaciones' => 'Evaluaciones',
            'materiales' => 'Materiales',
        ];

        return array_map(
            fn($service) => $serviceNames[$service] ?? $service,
            $this->affected_services ?? []
        );
    }

    /**
     * Verifica si la regla debe aplicarse basada en los días transcurridos
     */
    public function shouldApply(int $daysOverdue): bool
    {
        return $this->active && $daysOverdue >= $this->days_after_due;
    }
}
