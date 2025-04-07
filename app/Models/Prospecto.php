<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prospecto extends Model
{
    use HasFactory;

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
        'departamento',
        'municipio',
        // Nuevos campos para auditoría
        'created_by',
        'updated_by',
        'deleted_by'
    ];
}
