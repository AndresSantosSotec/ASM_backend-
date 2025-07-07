<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentRule;
use App\Models\PaymentRuleNotification;
use Illuminate\Http\Request;

class PaymentRuleNotificationController extends Controller
{
    public function store(Request $request, $ruleId)
    {
        $rule = PaymentRule::findOrFail($ruleId);

        $data = $request->validate([
            'type' => 'nullable|string',
            'offset_days' => 'required|integer',
            'message' => 'nullable|string',
        ]);

        $notification = $rule->notifications()->create($data);

        return response()->json(['data' => $notification], 201);
    }

    public function update(Request $request, $ruleId, $notificationId)
    {
        $rule = PaymentRule::findOrFail($ruleId);
        $notification = $rule->notifications()->findOrFail($notificationId);

        $data = $request->validate([
            'type' => 'sometimes|nullable|string',
            'offset_days' => 'sometimes|integer',
            'message' => 'nullable|string',
        ]);

        $notification->update($data);

        return response()->json(['data' => $notification]);
    }

    public function destroy($ruleId, $notificationId)
    {
        $rule = PaymentRule::findOrFail($ruleId);
        $notification = $rule->notifications()->findOrFail($notificationId);
        $notification->delete();

        return response()->json(null, 204);
    }
}
