<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'prospecto_id',
        'amount',
        'due_date',
        'status',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function prospecto()
    {
        return $this->belongsTo(Prospecto::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
