<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrecioConvenioPrograma extends Model
{
    protected $table = 'tb_precios_convenio_programa';
    public $timestamps = false;

    protected $fillable = [
        'convenio_id',
        'programa_id',
        'inscripcion',
        'cuota_mensual',
        'meses',
    ];

    protected $casts = [
        'inscripcion'     => 'decimal:2',
        'cuota_mensual'   => 'decimal:2',
        'meses'           => 'integer',
    ];

    /**
     * Convenio al que pertenece este precio
     */
    public function convenio()
    {
        return $this->belongsTo(Convenio::class, 'convenio_id');
    }

    /**
     * Programa al que aplica este precio
     */
    public function programa()
    {
        return $this->belongsTo(Programa::class, 'programa_id');
    }
}
