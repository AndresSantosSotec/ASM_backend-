<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pais extends Model
{
    use HasFactory;

    // Especifica la tabla asociada al modelo
    protected $table = 'paises';

    // Define la clave primaria
    protected $primaryKey = 'id';

    // Define los campos que se pueden llenar masivamente
    protected $fillable = ['nombre'];

    // Relación: Un país tiene muchos departamentos
    public function departamentos()
    {
        return $this->hasMany(Departamento::class);
    }
}