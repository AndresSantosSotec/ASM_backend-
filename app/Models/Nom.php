<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nom extends Model
{
    use HasFactory;
        // Define el nombre de la tabla (opcional si ya sigue la convención de nombres)
        protected $table = 'nom';

        // Define los campos que se pueden llenar masivamente
        protected $fillable = ['nombre_tipo'];
    
        /**
         * Relación con la tabla `column_configurations`.
         * Esto permite obtener todas las configuraciones de columnas asociadas a este tipo.
         */
        public function columnConfigurations()
        {
            return $this->hasMany(ColumnConfiguration::class, 'id_tipo', 'id_tipo'); // Clave foránea y clave primaria
        }
}
