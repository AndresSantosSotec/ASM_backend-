<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prospecto_id' => 'required|exists:prospectos,id',
            'amount' => 'required|numeric',
            'due_date' => 'required|date',
            'status' => 'required|string',
            'description' => 'nullable|string',
        ];
    }
}
