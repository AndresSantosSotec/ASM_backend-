<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Convenio extends Model
{
    use HasFactory;

    protected $table = 'tb_convenio';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    /**
     * Precios específicos de este convenio por programa
     */
    public function preciosPorPrograma()
    {
        return $this->hasMany(PrecioConvenioPrograma::class, 'convenio_id');
    }
}
