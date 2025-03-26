<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    use HasFactory;

    // Especifica la tabla asociada al modelo
    protected $table = 'departamentos';

    // Define la clave primaria
    protected $primaryKey = 'id';

    // Define los campos que se pueden llenar masivamente
    protected $fillable = ['nombre', 'pais_id'];

    // Relación: Un departamento pertenece a un país
    public function pais()
    {
        return $this->belongsTo(Pais::class);
    }

    // Relación: Un departamento tiene muchos municipios
    public function municipios()
    {
        return $this->hasMany(Municipio::class);
    }
}
