<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Actividades extends Model
{
    use HasFactory;

     // Desactiva los timestamps si no son necesarios
     public $timestamps = true; // Cambiar a false si no se necesitan

     // Define la tabla si es diferente al nombre del modelo
     protected $table = 'tb_actividades'; // Cambiar si el nombre de la tabla es diferente
 
     // Define la clave primaria si es diferente a 'id'
     protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'actividades',
        // Nuevos campos para auditoría
        'created_at',
        'updated_at'
    ];

}
