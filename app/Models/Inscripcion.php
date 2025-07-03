<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Inscripcion extends Model
{
    use HasFactory;

    protected $fillable = [
        'prospecto_id',
        'course_id',
        'semestre',
        'credits',
        'calificacion',
    ];

    public function prospecto()
    {
        return $this->belongsTo(Prospecto::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
