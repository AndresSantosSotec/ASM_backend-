<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\InscripcionesImport;
use Illuminate\Support\Facades\Log;

class EstudiantesImportController extends Controller
{
    public function uploadExcel(Request $request)
    {
        $request->validate([
            'file'    => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'confirm' => 'sometimes|boolean',
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        try {
            $file   = $request->file('file');
            $import = new InscripcionesImport();

            Excel::import($import, $file);

            // Reportar filas que fallaron validación
            if ($import->failures()->isNotEmpty()) {
                return response()->json([
                    'status'   => 'partial',
                    'message'  => 'Algunas filas se saltaron por errores de validación.',
                    'failures' => $import->failures()->map(fn($f) => [
                        'row'    => $f->row(),
                        'column' => $f->attribute(),
                        'errors' => $f->errors(),
                        'values' => $f->values(),
                    ]),
                ], 207);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Importación de estudiantes completada.',
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Error al importar estudiantes: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al importar estudiantes: ' . $e->getMessage(),
            ], 500);
        }
    }
}
