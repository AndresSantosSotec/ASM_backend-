<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'students'
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