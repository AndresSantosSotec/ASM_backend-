<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
