<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Prospecto;
use App\Models\Programa;
//- use App\Models\TbConvenio as Convenio;
use App\Models\Convenio;
use App\Models\CuotaProgramaEstudiante;
use App\Models\KardexPago;
use \Illuminate\Database\Eloquent\Factories\HasFactory;

class EstudiantePrograma extends Model
{
    use SoftDeletes;

    protected $table = 'estudiante_programa';
    protected $fillable = [
      'prospecto_id', 'programa_id', 'convenio_id',
      'fecha_inicio', 'fecha_fin', 'duracion_meses',
      'inscripcion', 'cuota_mensual', 'inversion_total',
      'created_by','updated_by','deleted_by'
    ];
    protected $casts = [
      'fecha_inicio' => 'date',
      'fecha_fin'    => 'date',
      'inscripcion'  => 'decimal:2',
      'cuota_mensual'=> 'decimal:2',
      'inversion_total' => 'decimal:2',
    ];

    public function prospecto()
    {
        return $this->belongsTo(Prospecto::class);
    }

    public function programa()
    {
        return $this->belongsTo(Programa::class, 'programa_id');
    }

    public function convenio()
    {

      return $this->belongsTo(Convenio::class, 'convenio_id');
    }

    public function cuotas()
    {
        return $this->hasMany(CuotaProgramaEstudiante::class, 'estudiante_programa_id');
    }

    public function pagos()
    {
        return $this->hasMany(KardexPago::class, 'estudiante_programa_id');
    }
}
