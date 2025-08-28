<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;

class PaymentExceptionCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'due_day_override',
        'skip_late_fee',
        'allow_partial_payments',
        'skip_blocking',
        'additional_rules',
        'active',
    ];

    protected $casts = [
        'due_day_override' => 'integer',
        'skip_late_fee' => 'boolean',
        'allow_partial_payments' => 'boolean',
        'skip_blocking' => 'boolean',
        'additional_rules' => 'array',
        'active' => 'boolean',
    ];

    /**
     * Scope para categorías activas
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Relación con prospectos (estudiantes) usando tabla pivote
     */
    public function prospectos(): BelongsToMany
    {
        return $this->belongsToMany(
            Prospecto::class,
            'prospecto_exception_categories',
            'payment_exception_category_id',
            'prospecto_id'
        )
        ->withPivot('effective_from', 'effective_until', 'notes')
        ->withTimestamps();
    }

    /**
     * Obtener prospectos que tienen esta categoría activa en una fecha específica
     */
    public function prospectosActiveOn(Carbon $date = null): BelongsToMany
    {
        $date = $date ?? now();

        return $this->prospectos()
            ->wherePivot(function ($query) use ($date) {
                $query->where(function ($q) use ($date) {
                    // Casos donde la excepción está vigente:
                    $q->where(function ($subQ) use ($date) {
                        // Sin fecha de inicio O fecha de inicio <= fecha actual
                        $subQ->whereNull('effective_from')
                             ->orWhere('effective_from', '<=', $date);
                    })
                    ->where(function ($subQ) use ($date) {
                        // Sin fecha de fin O fecha de fin >= fecha actual
                        $subQ->whereNull('effective_until')
                             ->orWhere('effective_until', '>=', $date);
                    });
                });
            });
    }

    /**
     * Verifica si la categoría está activa para una fecha específica
     */
    public function isActiveForDate(Carbon $date = null): bool
    {
        if (!$this->active) {
            return false;
        }

        $date = $date ?? now();

        // Aquí puedes agregar lógica adicional de fechas si la necesitas
        return true;
    }

    /**
     * Verifica si un estudiante debe ser exentado de mora
     */
    public function shouldSkipLateFee(): bool
    {
        return $this->skip_late_fee;
    }

    /**
     * Obtiene el día de vencimiento personalizado o null si usa el general
     */
    public function getCustomDueDay(): ?int
    {
        return $this->due_day_override;
    }

    /**
     * Verifica si permite pagos parciales
     */
    public function allowsPartialPayments(): bool
    {
        return $this->allow_partial_payments;
    }

    /**
     * Verifica si debe ser exentado de bloqueo
     */
    public function shouldSkipBlocking(): bool
    {
        return $this->skip_blocking;
    }
}
