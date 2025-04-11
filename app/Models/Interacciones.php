<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interacciones extends Model
{
    use HasFactory;
    
    // Mantiene los timestamps (true si los necesitas)
    public $timestamps = true;

    // Define la tabla asociada al modelo
    protected $table = 'tb_interacciones';

    // Define la clave primaria si es diferente a 'id'
    protected $primaryKey = 'id';

    // Campos asignables en masa de acuerdo a la nueva estructura de la tabla
    protected $fillable = [
        'id',
        'id_lead',           // Nuevo campo para almacenar el ID del lead
        'id_asesor',
        'id_actividades',
        'defec_interaccion',
        'duracion',
        'notas',
        'created_by',
        'created_at',
        'updated_at'
    ];
}
