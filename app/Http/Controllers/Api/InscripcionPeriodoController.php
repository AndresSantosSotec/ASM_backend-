<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InscripcionPeriodo;
use Illuminate\Validation\Rule;

class InscripcionPeriodoController extends Controller
{
    /**
     * Listar todas las inscripciones (con periodo y opcionalmente estudiante)
     */
    public function index()
    {
        $inscripciones = InscripcionPeriodo::with(['periodo'/*, 'estudiante'*/])->get();
        return response()->json($inscripciones);
    }

    /**
     * Registrar una nueva inscripción en un periodo
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'periodo_id'    => 'required|exists:tb_periodos_inscripcion,id',
            'estudiante_id' => 'required|exists:estudiantes,id',
            'estado'        => 'string|in:pendiente,confirmada,cancelada'
        ]);

        $inscripcion = InscripcionPeriodo::create($data);
        return response()->json($inscripcion->load('periodo'/*, 'estudiante'*/), 201);
    }

    /**
     * Mostrar una inscripción específica
     */
    public function show($id)
    {
        $inscripcion = InscripcionPeriodo::with(['periodo'/*, 'estudiante'*/])->findOrFail($id);
        return response()->json($inscripcion);
    }

    /**
     * Actualizar el estado o datos de una inscripción
     */
    public function update(Request $request, $id)
    {
        $inscripcion = InscripcionPeriodo::findOrFail($id);

        $data = $request->validate([
            'estado'        => 'string|in:pendiente,confirmada,cancelada',
            // no permitimos cambiar periodo_id o estudiante_id aquí,
            // si lo necesitas, añádelos con validación apropiada
        ]);

        $inscripcion->update($data);
        return response()->json($inscripcion->load('periodo'/*, 'estudiante'*/));
    }

    /**
     * Eliminar una inscripción
     */
    public function destroy($id)
    {
        $inscripcion = InscripcionPeriodo::findOrFail($id);
        $inscripcion->delete();
        return response()->noContent();
    }
}
