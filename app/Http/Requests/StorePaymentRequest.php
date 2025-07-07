<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estudiante_programa_id' => 'required|exists:estudiante_programa,id',
            'cuota_id' => 'nullable|exists:cuotas_programa_estudiante,id',
            'fecha_pago' => 'required|date',
            'monto_pagado' => 'required|numeric',
            'metodo_pago' => 'nullable|string',
            'observaciones' => 'nullable|string',
        ];
    }
}
