<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Programa;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'area',
        'credits',
        'start_date',
        'end_date',
        'schedule',
        'duration',
        'facilitator_id',
        'status',
        'students',
        'carrera',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    // Relación con el facilitador (docente)
    public function facilitator()
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    // Relación con el programa al que pertenece el curso
    public function program()
    {
        return $this->belongsTo(Programa::class, 'carrera');
    }

    public function prospectos()
    {
        return $this->belongsToMany(
            Prospecto::class,
            'curso_prospecto',   // tabla pivote
            'course_id',         // FK que apunta a courses.id
            'prospecto_id'       // FK que apunta a prospectos.id
        )->withTimestamps();
    }
}
