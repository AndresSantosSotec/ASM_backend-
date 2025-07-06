<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'prospecto_id',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function prospecto()
    {
        return $this->belongsTo(Prospecto::class);
    }

    public function installments()
    {
        return $this->hasMany(PaymentPlanInstallment::class);
    }
}
