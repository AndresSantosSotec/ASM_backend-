<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with(['prospecto', 'invoice']);
        if ($request->filled('prospecto_id')) {
            $query->where('prospecto_id', $request->prospecto_id);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'prospecto_id' => 'required|exists:prospectos,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'amount' => 'required|numeric',
            'method' => 'required|string',
            'status' => 'required|string',
            'paid_at' => 'nullable|date',
            'reference' => 'nullable|string',
        ]);

        $payment = Payment::create($data);

        return response()->json(['data' => $payment], 201);
    }
}
