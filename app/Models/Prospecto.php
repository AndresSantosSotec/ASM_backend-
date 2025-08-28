<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Inscripcion;
use App\Models\GpaHist;
use App\Models\Achievement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\CollectionLog;
use App\Models\ReconciliationRecord;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;


class Prospecto extends Model
{
    use HasFactory;

    // Tabla y clave primaria
    protected $table = 'prospectos';
    protected $primaryKey = 'id';

    // Laravel llevará automáticamente created_at y updated_at
    public $timestamps = true;

    // Campos que puedes rellenar masivamente
    protected $fillable = [
        'fecha',
        'nombre_completo',
        'telefono',
        'correo_electronico',
        'genero',
        'empresa_donde_labora_actualmente',
        'puesto',
        'notas_generales',
        'observaciones',
        'interes',
        'nota1',
        'nota2',
        'nota3',
        'cierre',
        'status',
        'correo_corporativo',
        'pais_origen',
        'pais_residencia',
        'numero_identificacion',
        'fecha_nacimiento',
        'modalidad',
        'fecha_inicio_especifica',
        'fecha_taller_reduccion',
        'fecha_taller_integracion',
        'medio_conocimiento_institucion',
        'metodo_pago',
        'departamento',
        'municipio',
        'direccion_residencia',
        'telefono_corporativo',
        'direccion_empresa',
        'ultimo_titulo_obtenido',
        'institucion_titulo',
        'anio_graduacion',
        'cantidad_cursos_aprobados',
        'monto_inscripcion',
        'convenio_pago_id',
        'dia_estudio',
        // auditoría
        'carnet',
        'activo',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    // Casts para fechas y decimales
    protected $casts = [
        'fecha'                         => 'date',
        'fecha_nacimiento'              => 'date',
        'fecha_inicio_especifica'       => 'date',
        'fecha_taller_reduccion'        => 'date',
        'fecha_taller_integracion'      => 'date',
        'anio_graduacion'               => 'integer',
        'cantidad_cursos_aprobados'     => 'integer',
        'monto_inscripcion'             => 'decimal:2',
        'activo' => 'boolean',
    ];

    /** Relaciones de usuario (auditoría) */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Convenio corporativo, si existe */
    public function convenio()
    {
        return $this->belongsTo(Convenio::class, 'convenio_pago_id');
    }

    /** Inscripciones académicas ligadas a este prospecto */
    public function programas()
    {
        return $this->hasMany(EstudiantePrograma::class, 'prospecto_id');
    }

    public function asesor()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documentos()
    {
        return $this->hasMany(ProspectosDocumento::class, 'prospecto_id');
    }

    public function courses()
    {
        return $this->belongsToMany(
            Course::class,
            'curso_prospecto',    // nombre de la tabla pivote
            'prospecto_id',       // FK en la tabla pivote que apunta a prospectos.id
            'course_id'           // FK en la tabla pivote que apunta a courses.id
        )->withTimestamps();
    }

    public function inscripciones()
    {
        return $this->hasMany(Inscripcion::class);
    }

    public function gpaHist()
    {
        return $this->hasMany(GpaHist::class);
    }

    public function achievements()
    {
        return $this->hasMany(Achievement::class);
    }

    /** Finanzas */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }


    /** Planes y pagos reales */
    public function cuotas()
    {
        return $this->hasManyThrough(
            CuotaProgramaEstudiante::class,
            EstudiantePrograma::class,
            'prospecto_id',
            'estudiante_programa_id'
        );
    }

    public function kardexPagos()
    {
        return $this->hasManyThrough(
            KardexPago::class,
            EstudiantePrograma::class,
            'prospecto_id',
            'estudiante_programa_id'
        );
    }


    public function paymentPlans()
    {
        return $this->hasMany(PaymentPlan::class);
    }

    public function collectionLogs()
    {
        return $this->hasMany(CollectionLog::class);
    }

    public function reconciliationRecords()
    {
        return $this->hasMany(ReconciliationRecord::class);
    }

    /**
     * Genera un carnet único con el formato ASM<year><correlativo>.
     * Busca el último carnet del año actual y suma 1 al correlativo.
     */
    public static function generateCarnet(): string
    {
        $year = now()->year;
        $prefix = 'ASM' . $year;

        $max = static::where('carnet', 'like', $prefix . '%')
            ->pluck('carnet')
            ->map(function ($carnet) use ($prefix) {
                $num = (int) preg_replace('/\D/', '', substr($carnet, strlen($prefix)));
                return $num;
            })
            ->max();

        $correlative = ($max ?? 0) + 1;

        return $prefix . $correlative;
    }

    public function getBalance(): float
    {

        $deuda = $this->cuotas()->sum('monto');
        $pagos = $this->kardexPagos()->sum('monto_pagado');
        return (float) ($deuda - $pagos);
    }

    public function isBlocked(): bool
    {

        return $this->cuotas()
            ->where('estado', '!=', 'pagado')
            ->whereDate('fecha_vencimiento', '<', now())

            ->exists();
    }

    public function exceptionCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            PaymentExceptionCategory::class,
            'prospecto_exception_categories',
            'prospecto_id',
            'payment_exception_category_id'
        )
            ->withPivot('effective_from', 'effective_until', 'notes')
            ->withTimestamps();
    }

    public function activeExceptionCategories(Carbon $date = null): BelongsToMany
    {
        $date = $date ?? now();

        return $this->exceptionCategories()
            ->where('payment_exception_categories.active', true)
            ->wherePivot(function ($query) use ($date) {
                $query->where(function ($q) use ($date) {
                    // Sin fecha de inicio O fecha de inicio <= fecha actual
                    $q->whereNull('effective_from')
                        ->orWhere('effective_from', '<=', $date);
                })
                    ->where(function ($q) use ($date) {
                        // Sin fecha de fin O fecha de fin >= fecha actual
                        $q->whereNull('effective_until')
                            ->orWhere('effective_until', '>=', $date);
                    });
            });
    }
    /**
     * Verifica si el prospecto tiene alguna excepción activa para una regla específica
     */
    public function hasActiveException(string $ruleType, Carbon $date = null): bool
    {
        $activeCategories = $this->activeExceptionCategories($date)->get();

        switch ($ruleType) {
            case 'late_fee':
                return $activeCategories->contains('skip_late_fee', true);
            case 'partial_payments':
                return $activeCategories->contains('allow_partial_payments', true);
            case 'blocking':
                return $activeCategories->contains('skip_blocking', true);
            default:
                return false;
        }
    }

    /**
     * Obtiene el día de vencimiento personalizado si tiene excepción activa
     */
    public function getCustomDueDay(Carbon $date = null): ?int
    {
        $activeCategories = $this->activeExceptionCategories($date)->get();

        // Retorna el primer día personalizado encontrado
        foreach ($activeCategories as $category) {
            if ($category->due_day_override) {
                return $category->due_day_override;
            }
        }

        return null;
    }

    /**
     * Verifica si está bloqueado considerando excepciones
     */
    public function isBlockedWithExceptions(): bool
    {
        // Si tiene excepción de bloqueo activa, nunca está bloqueado
        if ($this->hasActiveException('blocking')) {
            return false;
        }

        // Usar la lógica original de bloqueo
        return $this->isBlocked();
    }
}
