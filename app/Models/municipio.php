<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Municipio extends Model
{
    use HasFactory;

    // Especifica la tabla asociada al modelo
    protected $table = 'municipios';

    // Define la clave primaria
    protected $primaryKey = 'id';

    // Define los campos que se pueden llenar masivamente
    protected $fillable = ['nombre', 'departamento_id'];

    // Relación: Un municipio pertenece a un departamento
    public function departamento()
    {
        return $this->belongsTo(Departamento::class);
    }
}