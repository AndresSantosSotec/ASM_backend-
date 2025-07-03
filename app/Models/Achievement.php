<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Achievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'prospecto_id',
        'tipo',
        'semestre',
    ];

    public function prospecto()
    {
        return $this->belongsTo(Prospecto::class);
    }
}
