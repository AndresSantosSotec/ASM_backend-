<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PeriodoInscripcion;
use Illuminate\Validation\Rule;

class PeriodoInscripcionController extends Controller
{
    /**
     * Listar todos los periodos con sus programas asociados
     */
    public function index()
    {
        $periodos = PeriodoInscripcion::with('programas')->get();
        return response()->json($periodos);
    }

    /**
     * Crear un nuevo periodo
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'         => 'required|string|max:100',
            'codigo'         => 'required|string|max:50|unique:tb_periodos_inscripcion,codigo',
            'fecha_inicio'   => 'required|date',
            'fecha_fin'      => 'required|date|after_or_equal:fecha_inicio',
            'descripcion'    => 'nullable|string',
            'cupos_total'    => 'required|integer|min:0',
            'descuento'      => 'required|integer|min:0|max:100',
            'activo'         => 'boolean',
            'visible'        => 'boolean',
            'notificaciones' => 'boolean',
            'programas'      => 'array',               // IDs de programas
            'programas.*'    => 'exists:tb_programas,id',
        ]);

        $periodo = PeriodoInscripcion::create($data);

        if (!empty($data['programas'])) {
            $periodo->programas()->sync($data['programas']);
        }

        return response()->json($periodo->load('programas'), 201);
    }

    /**
     * Mostrar un periodo concreto
     */
    public function show($id)
    {
        $periodo = PeriodoInscripcion::with('programas')->findOrFail($id);
        return response()->json($periodo);
    }

    /**
     * Actualizar un periodo existente
     */
    public function update(Request $request, $id)
    {
        $periodo = PeriodoInscripcion::findOrFail($id);

        $data = $request->validate([
            'nombre'         => 'sometimes|required|string|max:100',
            'codigo'         => ['sometimes','required','string','max:50',
                                  Rule::unique('tb_periodos_inscripcion','codigo')->ignore($periodo->id)],
            'fecha_inicio'   => 'sometimes|required|date',
            'fecha_fin'      => 'sometimes|required|date|after_or_equal:fecha_inicio',
            'descripcion'    => 'nullable|string',
            'cupos_total'    => 'sometimes|required|integer|min:0',
            'descuento'      => 'sometimes|required|integer|min:0|max:100',
            'activo'         => 'boolean',
            'visible'        => 'boolean',
            'notificaciones' => 'boolean',
            'programas'      => 'array',
            'programas.*'    => 'exists:tb_programas,id',
        ]);

        $periodo->update($data);

        if (array_key_exists('programas', $data)) {
            $periodo->programas()->sync($data['programas']);
        }

        return response()->json($periodo->load('programas'));
    }

    /**
     * Eliminar un periodo
     */
    public function destroy($id)
    {
        $periodo = PeriodoInscripcion::findOrFail($id);
        $periodo->delete();
        return response()->noContent();
    }
}
