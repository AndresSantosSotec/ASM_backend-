<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Citas;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CitasController extends Controller
{
    // Listar todas las citas (filtrando según el usuario autenticado)
    // app/Http/Controllers/Api/CitasController.php

    public function index()
{
    $user = auth()->user();
    if (!$user) {
        return response()->json(['error' => 'Usuario no autenticado'], 401);
    }

    $isAdmin = strtolower($user->rol) === 'administrador';

    $q = Citas::with(['creador']); // sin columns forzados

    $citas = $isAdmin
        ? $q->get()
        : $q->where('createby', $user->id)->get();

    // Normaliza el output para el frontend:
    $out = $citas->map(function ($cita) {
        $u = $cita->creador;
        // intenta varios posibles nombres de columna
        $nombre = $u->nombre ?? $u->name ?? $u->username ?? $u->email ?? null;

        return [
            'id'             => $cita->id,
            'datecita'       => $cita->datecita,
            'descricita'     => $cita->descricita,
            'createby'       => $cita->createby,
            'creado_por'     => $u ? [
                'id'    => $u->id,
                'nombre'=> $nombre,
                'rol'   => $u->rol ?? null,
            ] : null,
            'fecha_creacion' => optional($cita->created_at)->toDateTimeString(),
        ];
    });

    return response()->json([
        'message' => 'Datos de citas obtenidos con éxito',
        'data'    => $out,
    ]);
}



    // Mostrar una sola cita
    public function show($id)
    {
        $cita = Citas::find($id);
        if ($cita) {
            return response()->json($cita);
        } else {
            return response()->json(['message' => 'Cita not found'], 404);
        }
    }

    // Crear una nueva cita
    public function store(Request $request)
    {
        // Verificar que el usuario esté autenticado
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Validar los datos de entrada; se espera que el frontend envíe "datecita" y "descricita"
        $validated = $request->validate([
            'datecita'   => 'required|date',
            'descricita' => 'required|string',
        ]);

        // Convertir la fecha usando Carbon para garantizar el formato correcto
        $date = Carbon::parse($validated['datecita']);

        // Preparar el arreglo para insertar; se asigna "createby" con el id del usuario autenticado
        $data = [
            'datecita'   => $date,
            'descricita' => $validated['descricita'],
            'createby'   => $user->id,
        ];

        try {
            $cita = Citas::create($data);
            return response()->json([
                'message' => 'Cita guardada con éxito',
                'data'    => $cita
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al guardar la cita',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // Actualizar una cita existente
    public function update(Request $request, $id)
    {
        $cita = Citas::find($id);
        if (!$cita) {
            return response()->json(['message' => 'Cita not found'], 404);
        }

        // Validar los campos a actualizar; se pueden actualizar "datecita" y "descricita"
        $validated = $request->validate([
            'datecita'   => 'sometimes|required|date',
            'descricita' => 'sometimes|required|string',
        ]);

        // Si se envía "datecita", parsearla
        if (isset($validated['datecita'])) {
            $validated['datecita'] = Carbon::parse($validated['datecita']);
        }

        try {
            $cita->update($validated);
            return response()->json([
                'message' => 'Cita actualizada con éxito',
                'data'    => $cita
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la cita',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // Eliminar una cita
    public function destroy($id)
    {
        $cita = Citas::find($id);
        if ($cita) {
            $cita->delete();
            return response()->json(['message' => 'Cita deleted']);
        } else {
            return response()->json(['message' => 'Cita not found'], 404);
        }
    }
}