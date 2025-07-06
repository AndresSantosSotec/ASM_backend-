<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'prospecto_id',
        'invoice_id',
        'amount',
        'method',
        'status',
        'paid_at',
        'reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function prospecto()
    {
        return $this->belongsTo(Prospecto::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
