<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactoEnviado extends Model
{
    use HasFactory;

    // Si tu tabla es 'contactos_enviados' esto es opcional porque Laravel infiere correctamente
    protected $table = 'contactos_enviados';

    // Campos que permitido llenar masivamente
    protected $fillable = [
        'prospecto_id',
        'canal',
        'tipo_contacto',
        'fecha_envio',
        'resultado',
        'observaciones',
        'creado_por',
    ];

    /**
     * Relación con Prospecto
     */
    public function prospecto()
    {
        return $this->belongsTo(Prospecto::class);
    }
}
