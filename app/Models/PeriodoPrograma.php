<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodoPrograma extends Model
{
    protected $table = 'tb_periodo_programa';
    public    $timestamps = false;

    protected $fillable = [
        'periodo_id',
        'programa_id',
    ];
}
