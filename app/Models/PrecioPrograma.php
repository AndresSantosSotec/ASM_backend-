<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrecioPrograma extends Model
{
    protected $table = 'tb_precios_programa';
    public $timestamps = false;

    protected $fillable = [
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
     * Programa al que aplica este precio estándar
     */
    public function programa()
    {
        return $this->belongsTo(Programa::class, 'programa_id');
    }
}
