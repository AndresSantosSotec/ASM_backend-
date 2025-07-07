<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRuleNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_rule_id',
        'type',
        'offset_days',
        'message',
    ];

    protected $casts = [
        'offset_days' => 'integer',
    ];

    public function rule()
    {
        return $this->belongsTo(PaymentRule::class, 'payment_rule_id');
    }
}
