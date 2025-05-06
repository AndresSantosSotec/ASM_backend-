<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvisorCommissionRate extends Model
{
    use HasFactory;

    /**
     * Tabla asociada (opcional si sigue convención).
     */
    protected $table = 'advisor_commission_rates';

    /**
     * Campos asignables masivamente.
     */
    protected $fillable = [
        'user_id',
        'rate',           // tasa personalizada (%) en formato decimal: 12.50 → 12.5%
    ];

    /**
     * Casts de columnas a tipos nativos.
     */
    protected $casts = [
        'rate'    => 'decimal:2',
        'user_id' => 'integer',
    ];

    /**
     * Relación: cada tasa pertenece a un asesor (usuario).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
