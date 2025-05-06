<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    use HasFactory;

    protected $table = 'commissions';

    protected $fillable = [
        'user_id',
        'period_start',
        'period_end',
        'total_income',
        'conversions',
        'rate_applied',
        'commission_amount',
        'difference',
        'config_id',
    ];

    protected $casts = [
        'user_id'          => 'integer',
        'period_start'     => 'date',
        'period_end'       => 'date',
        'total_income'     => 'decimal:2',
        'conversions'      => 'integer',
        'rate_applied'     => 'decimal:2',
        'commission_amount'=> 'decimal:2',
        'difference'       => 'decimal:2',
        'config_id'        => 'integer',
    ];

    /**
     * La configuración global usada para este cálculo.
     */
    public function config()
    {
        return $this->belongsTo(CommissionConfig::class, 'config_id');
    }

    /**
     * El asesor (usuario) al que pertenece esta comisión.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
