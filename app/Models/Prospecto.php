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
use App\Models\CuotaProgramaEstudiante;
use App\Models\KardexPago;

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
    
}
