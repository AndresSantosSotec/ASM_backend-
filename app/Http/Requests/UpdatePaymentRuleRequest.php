<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'due_day' => 'sometimes|integer|between:1,31',
            'late_fee_amount' => 'sometimes|numeric|min:0',
            'block_after_months' => 'sometimes|integer|min:0',
            'send_automatic_reminders' => 'sometimes|boolean',
            'gateway_config' => 'sometimes|array',
        ];
    }
}
