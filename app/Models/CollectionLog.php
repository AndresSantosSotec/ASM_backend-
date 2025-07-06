<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'prospecto_id',
        'date',
        'type',
        'notes',
        'agent',
        'next_contact_at',
    ];

    protected $casts = [
        'date' => 'date',
        'next_contact_at' => 'datetime',
    ];

    public function prospecto()
    {
        return $this->belongsTo(Prospecto::class);
    }
}
