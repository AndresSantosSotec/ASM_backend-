<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'due_day',
        'late_fee_amount',
        'block_after_months',
        'send_automatic_reminders',
        'gateway_config',
    ];

    protected $casts = [
        'due_day' => 'integer',
        'late_fee_amount' => 'decimal:2',
        'block_after_months' => 'integer',
        'send_automatic_reminders' => 'boolean',
        'gateway_config' => 'array',
    ];

    /**
     * Relación con notificaciones
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(PaymentRuleNotification::class);
    }

    /**
     * Relación con reglas de bloqueo
     */
    public function blockingRules(): HasMany
    {
        return $this->hasMany(PaymentRuleBlockingRule::class);
    }

    /**
     * Obtener reglas de bloqueo activas ordenadas por días
     */
    public function activeBlockingRules(): HasMany
    {
        return $this->blockingRules()->active()->orderByDays();
    }

    /**
     * Obtener la regla de bloqueo aplicable para ciertos días de atraso
     */
    public function getApplicableBlockingRule(int $daysOverdue): ?PaymentRuleBlockingRule
    {
        return $this->activeBlockingRules()
            ->where('days_after_due', '<=', $daysOverdue)
            ->orderBy('days_after_due', 'desc')
            ->first();
    }
}
