<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProspectosImport;
use Illuminate\Support\Facades\Log;

class ProspectosImportController extends Controller
{
    /**
     * Procesa el archivo Excel e importa prospectos.
     * Se espera que el endpoint esté protegido por autenticación.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadExcel(Request $request)
    {
        // Validar el archivo recibido
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480', // Máximo 20MB
        ]);

        // Obtener el usuario autenticado
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        try {
            $file = $request->file('file');

            // Usar el id del usuario autenticado para 'created_by'
            $userId = $user->id;

            $import = new ProspectosImport($userId);
            Excel::import($import, $file);

            return response()->json([
                'message' => 'Prospectos importados correctamente',
                'status'  => 'success',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al importar prospectos: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al importar prospectos: ' . $e->getMessage(),
                'status'  => 'error'
            ], 500);
        }
    }
}
