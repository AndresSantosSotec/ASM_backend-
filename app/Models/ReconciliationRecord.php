<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReconciliationRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'prospecto_id',
        'bank',
        'reference',
        'amount',
        'date',
        'auth_number',
        'status',
        'uploaded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    public function prospecto()
    {
        return $this->belongsTo(Prospecto::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
