<?php

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

    public $timestamps = true;

    protected $fillable = [
        'prospecto_id',
        'tipo_documento',
        'ruta_archivo',
        'subida_at',
        'estado',          // ← agregado
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
      'subida_at'  => 'datetime',
      'created_at' => 'datetime',
      'updated_at' => 'datetime',
      'deleted_at' => 'datetime',
      'estado'     => 'string',  // opcional
    ];

    public function prospecto()
    {
        return $this->belongsTo(Prospecto::class, 'prospecto_id');
    }
}
