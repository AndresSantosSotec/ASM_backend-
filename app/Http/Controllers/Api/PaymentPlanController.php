<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use Illuminate\Http\Request;

class PaymentPlanController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentPlan::with('prospecto');

        if ($request->filled('prospecto_id')) {
            $query->where('prospecto_id', $request->prospecto_id);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'prospecto_id' => 'required|exists:prospectos,id',
            'total_amount' => 'required|numeric',
            'status'       => 'required|string',
        ]);

        $plan = PaymentPlan::create($data);

        return response()->json(['data' => $plan], 201);
    }

    public function show(PaymentPlan $paymentPlan)
    {
        $paymentPlan->load('installments');
        return response()->json(['data' => $paymentPlan]);
    }

    public function update(Request $request, PaymentPlan $paymentPlan)
    {
        $data = $request->validate([
            'total_amount' => 'sometimes|numeric',
            'status'       => 'sometimes|string',
        ]);

        $paymentPlan->update($data);

        return response()->json(['data' => $paymentPlan]);
    }

    public function destroy(PaymentPlan $paymentPlan)
    {
        $paymentPlan->delete();

        return response()->json(['message' => 'deleted']);
    }

    /* ===== Installments ===== */
    public function indexInstallments(PaymentPlan $paymentPlan)
    {
        return response()->json(['data' => $paymentPlan->installments]);
    }

    public function storeInstallment(Request $request, PaymentPlan $paymentPlan)
    {
        $data = $request->validate([
            'due_date' => 'required|date',
            'amount'   => 'required|numeric',
            'status'   => 'required|string',
        ]);

        $installment = $paymentPlan->installments()->create($data);

        return response()->json(['data' => $installment], 201);
    }

    public function showInstallment(PaymentPlanInstallment $installment)
    {
        return response()->json(['data' => $installment]);
    }

    public function updateInstallment(Request $request, PaymentPlanInstallment $installment)
    {
        $data = $request->validate([
            'due_date' => 'sometimes|date',
            'amount'   => 'sometimes|numeric',
            'status'   => 'sometimes|string',
        ]);

        $installment->update($data);

        return response()->json(['data' => $installment]);
    }

    public function destroyInstallment(PaymentPlanInstallment $installment)
    {
        $installment->delete();

        return response()->json(['message' => 'deleted']);
    }
}
