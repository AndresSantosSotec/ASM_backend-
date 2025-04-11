<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ColumnConfiguration extends Model
{
    use HasFactory;

    //Se definen los campos a llenar en la tabla 

    protected $fillable = ['id_tipo', 'column_name', 'excel_column_name', 'column_number'];

    /**
     * Relación con la tabla `nom`.
     * Esto conecta cada configuración de columna con su tipo en la tabla `nom`.
     */
    public function nom()
    {
        return $this->belongsTo(Nom::class, 'id_tipo', 'id_tipo'); // Clave foránea y clave primaria
    }
}
