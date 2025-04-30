<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EstudiantePrograma;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EstudianteProgramaController extends Controller
{
    /**
     * Registrar uno o varios programas académicos para un prospecto.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'prospecto_id'  => 'required|exists:prospectos,id',
            'programas'     => 'required|array|min:1', // Mínimo 1 programa
            'programas.*.programa_id'     => 'required|exists:tb_programas,id',
            'programas.*.duracion_meses'  => 'required|integer|min:1',
            'programas.*.fecha_inicio'    => 'required|date',
            'programas.*.fecha_fin'       => 'required|date|after_or_equal:programas.*.fecha_inicio',
            'programas.*.inscripcion'     => 'required|numeric|min:0',
            'programas.*.cuota_mensual'   => 'required|numeric|min:0',
            'programas.*.inversion_total' => 'required|numeric|min:0',
            'convenio_id'   => 'nullable|exists:tb_convenio,id', // opcional
        ]);

        $registros = [];

        DB::beginTransaction();
        try {
            foreach ($data['programas'] as $programa) {
                $nuevo = EstudiantePrograma::create([
                    'prospecto_id'    => $data['prospecto_id'],
                    'programa_id'     => $programa['programa_id'],
                    'duracion_meses'  => $programa['duracion_meses'],
                    'fecha_inicio'    => $programa['fecha_inicio'],
                    'fecha_fin'       => $programa['fecha_fin'],
                    'inscripcion'     => $programa['inscripcion'],
                    'cuota_mensual'   => $programa['cuota_mensual'],
                    'inversion_total' => $programa['inversion_total'],
                    'convenio_id'     => $data['convenio_id'] ?? null,
                    'created_by'      => Auth::id(),
                ]);
                $registros[] = $nuevo;
            }

            DB::commit();

            return response()->json([
                'message'   => 'Programas asignados correctamente.',
                'programas' => $registros,
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error al asignar programas.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
