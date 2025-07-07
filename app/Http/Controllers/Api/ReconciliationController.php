<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReconciliationRecord;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ReconciliationController extends Controller
{
    /**
     * Upload reconciliation file and store records as pending.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls'
        ]);

        $userId = Auth::id();
        $rows = Excel::toCollection(null, $request->file('file'))->first();
        $count = 0;

        foreach ($rows as $row) {
            $data = [
                'bank'        => $row['bank'] ?? $row[0] ?? '',
                'reference'   => $row['reference'] ?? $row[1] ?? '',
                'amount'      => $row['amount'] ?? $row[2] ?? 0,
                'date'        => isset($row['date']) ? Carbon::parse($row['date'])->format('Y-m-d') : (isset($row[3]) ? Carbon::parse($row[3])->format('Y-m-d') : now()->toDateString()),
                'auth_number' => $row['auth_number'] ?? $row[4] ?? null,
                'status'      => 'pendiente',
                'uploaded_by' => $userId,
            ];
            ReconciliationRecord::create($data);
            $count++;
        }

        return response()->json(['inserted' => $count], 201);
    }

    /**
     * List pending reconciliation records.
     */
    public function pending()
    {
        $records = ReconciliationRecord::where('status', 'pendiente')
            ->with(['prospecto', 'uploader'])
            ->get();

        return response()->json(['data' => $records]);
    }

    /**
     * Process pending records matching payments by reference and amount.
     */
    public function process()
    {
        $records = ReconciliationRecord::where('status', 'pendiente')->get();
        $matched = 0;

        foreach ($records as $rec) {
            $payment = Payment::where('reference', $rec->reference)
                ->where('amount', $rec->amount)
                ->first();

            if ($payment) {
                $rec->prospecto_id = $payment->prospecto_id;
                $rec->status = 'conciliado';
                $rec->save();

                $payment->status = 'conciliado';
                $payment->save();
                $matched++;
            }
        }

        return response()->json(['matched' => $matched]);
    }
}

