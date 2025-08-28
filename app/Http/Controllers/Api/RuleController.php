<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePaymentRuleRequest;
use App\Models\PaymentRule;
use Illuminate\Http\Request;

class RuleController extends Controller
{
    public function index()
    {
        return response()->json(PaymentRule::all());
    }

    public function update(UpdatePaymentRuleRequest $request, PaymentRule $rule)
    {
        $rule->update($request->validated());
        return response()->json($rule);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'due_day' => 'required|integer|min:1|max:31',
            'late_fee_amount' => 'required|numeric|min:0',
            'block_after_months' => 'nullable|integer|min:0',
            'send_automatic_reminders' => 'boolean',
            'gateway_config' => 'nullable|array',
        ]);

        $rule = PaymentRule::create($data);
        return response()->json($rule, 201);
    }

    public function show(PaymentRule $rule)
    {
        // Si luego necesitas las notificaciones: $rule->load('notifications');
        return response()->json($rule);
    }
    public function current()
    {
        // Si tu app tiene una sola regla vigente, exponemos la última creada.
        $rule = PaymentRule::latest('id')->first();

        if (!$rule) {
            return response()->json(['message' => 'No hay regla configurada.'], 404);
        }

        return response()->json($rule);
    }
}
