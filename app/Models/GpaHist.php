<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpaHist extends Model
{
    use HasFactory;

    protected $table = 'gpa_hist';

    protected $fillable = [
        'prospecto_id',
        'semestre',
        'gpa',
    ];

    public function prospecto()
    {
        return $this->belongsTo(Prospecto::class);
    }
}
