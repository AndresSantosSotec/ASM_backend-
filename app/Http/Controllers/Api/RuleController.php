<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePaymentRuleRequest;
use App\Models\PaymentRule;

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
}
