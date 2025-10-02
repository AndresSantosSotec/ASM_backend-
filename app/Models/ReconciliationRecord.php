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
        'bank_normalized',
        'reference_normalized',
        'fingerprint',
        'kardex_pago_id',
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

    public function kardexPago()
    {
        return $this->belongsTo(KardexPago::class, 'kardex_pago_id');
    }
}
