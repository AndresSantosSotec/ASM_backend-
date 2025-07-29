<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Inscripcion;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'moodle_id',
        'name','code','area','credits',
        'start_date','end_date','schedule','duration',
        'facilitator_id','status','students','origen'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function facilitator()
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    // NUEVA relación N-N
    public function programas()
    {
        return $this->belongsToMany(
            Programa::class,
            'programa_course',
            'course_id',
            'programa_id'
        )->withTimestamps();
    }

    public function prospectos()
    {
        return $this->belongsToMany(
            Prospecto::class,
            'curso_prospecto',
            'course_id',
            'prospecto_id'
        )->withTimestamps();
    }

    public function inscripciones()
    {
        return $this->hasMany(Inscripcion::class);
    }
}
