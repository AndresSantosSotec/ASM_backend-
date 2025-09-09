<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentRequest extends Model
{
    protected $table = 'payment_requests';
    
    protected $fillable = [
        'idempotency_key',
        'user_id',
        'request_payload',
        'response_payload',
        'response_status',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}