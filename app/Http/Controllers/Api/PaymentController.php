<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KardexPago;
use App\Models\CuotaProgramaEstudiante;
use Illuminate\Http\Request;
use App\Http\Requests\StorePaymentRequest;
use Illuminate\Support\Facades\Auth;


class PaymentController extends Controller
{
    public function index(Request $request)
    {

        $query = KardexPago::with(['estudiantePrograma.prospecto','cuota']);

        if ($request->filled('prospecto_id')) {
            $query->whereHas('estudiantePrograma', function ($q) use ($request) {
                $q->where('prospecto_id', $request->prospecto_id);
            });
        }
        if ($request->filled('estudiante_programa_id')) {
            $query->where('estudiante_programa_id', $request->estudiante_programa_id);

        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(StorePaymentRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = Auth::id();
        $payment = KardexPago::create($data);

        if ($payment->cuota_id) {
            CuotaProgramaEstudiante::where('id', $payment->cuota_id)->update([
                'estado' => 'pagado',
                'paid_at' => $payment->fecha_pago,
            ]);
        }


        return response()->json(['data' => $payment], 201);
    }
}
