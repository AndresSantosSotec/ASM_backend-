<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Convenio;

class ConvenioController extends Controller
{
    /**
     * GET  /api/convenios
     * Devuelve todos los convenios.
     */
    public function index()
    {
        $convenios = Convenio::all();
        return response()->json($convenios);
    }

    /**
     * POST /api/convenios
     * Crea un nuevo convenio.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'        => 'required|string|max:255',
            'descripcion'   => 'nullable|string',
            'activo'        => 'boolean',
        ]);

        // por defecto activo = true si no viene
        $data['activo'] = $data['activo'] ?? true;

        $convenio = Convenio::create($data);

        return response()->json($convenio, 201);
    }

    /**
     * GET /api/convenios/{id}
     * Muestra un convenio específico.
     */
    public function show($id)
    {
        $convenio = Convenio::findOrFail($id);
        return response()->json($convenio);
    }

    /**
     * PUT|PATCH /api/convenios/{id}
     * Actualiza un convenio.
     */
    public function update(Request $request, $id)
    {
        $convenio = Convenio::findOrFail($id);

        $data = $request->validate([
            'nombre'        => 'sometimes|required|string|max:255',
            'descripcion'   => 'sometimes|nullable|string',
            'activo'        => 'sometimes|boolean',
        ]);

        $convenio->update($data);

        return response()->json($convenio);
    }

    /**
     * DELETE /api/convenios/{id}
     * Elimina un convenio.
     */
    public function destroy($id)
    {
        $convenio = Convenio::findOrFail($id);
        $convenio->delete();

        // 204 = sin contenido
        return response()->json(null, 204);
    }
}
