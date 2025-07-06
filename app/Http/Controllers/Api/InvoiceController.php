<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with('prospecto');

        if ($request->filled('prospecto_id')) {
            $query->where('prospecto_id', $request->prospecto_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'prospecto_id' => 'required|exists:prospectos,id',
            'amount' => 'required|numeric',
            'due_date' => 'required|date',
            'status' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $invoice = Invoice::create($data);

        return response()->json(['data' => $invoice], 201);
    }

    public function update(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount' => 'sometimes|numeric',
            'due_date' => 'sometimes|date',
            'status' => 'sometimes|string',
            'description' => 'nullable|string',
        ]);

        $invoice->update($data);

        return response()->json(['data' => $invoice]);
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();

        return response()->json(['message' => 'deleted']);
    }
}
