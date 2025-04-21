<?php
// 2) Modelo Eloquent: app/Models/ProspectosDocumento.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProspectosDocumento extends Model
{
    use SoftDeletes;

    protected $table      = 'prospectos_documentos';
    protected $primaryKey = 'id';
    public    $incrementing = true;
    protected $keyType    = 'int';

    // Laravel llena created_at / updated_at automáticamente
    public $timestamps = true;

    // Campos que se pueden asignar en masa
    protected $fillable = [
        'prospecto_id',
        'tipo_documento',
        'ruta_archivo',
        'subida_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    // Fechas para mutadores
    protected $dates = [
        'subida_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // Relación al prospecto
    public function prospecto()
    {
        return $this->belongsTo(Prospecto::class, 'prospecto_id');
    }
}
