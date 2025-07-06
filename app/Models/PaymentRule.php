<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'due_day',
        'late_fee_amount',
        'block_after_months',
        'send_automatic_reminders',
        'gateway_config',
    ];

    protected $casts = [
        'late_fee_amount' => 'decimal:2',
        'send_automatic_reminders' => 'boolean',
        'gateway_config' => 'array',
    ];
}
