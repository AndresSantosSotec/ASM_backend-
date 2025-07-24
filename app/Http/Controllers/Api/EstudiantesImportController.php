<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\InscripcionesImport;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

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

            Log::info('Inicio de importación de estudiantes', [
                'user_id' => $user->id,
                'file'    => $file->getClientOriginalName(),
            ]);

            Excel::import($import, $file);

            $failures  = $import->failures();
            $rowErrors = $import->getRowErrors();

            if ($failures->isNotEmpty() || !empty($rowErrors)) {
                Log::warning('Importación finalizada con problemas', [
                    'failures'   => $failures,
                    'row_errors' => $rowErrors,
                ]);
                return response()->json([
                    'status'   => 'partial',
                    'message'  => 'Algunas filas se saltaron por errores de validación o procesamiento.',
                    'failures' => $failures->map(fn($f) => [
                        'row'    => $f->row(),
                        'column' => $f->attribute(),
                        'errors' => $f->errors(),
                        'values' => $f->values(),
                    ]),
                    'row_errors' => $rowErrors,
                ], 207);
            }

            Log::info('Importación de estudiantes completada con éxito');
            return response()->json([
                'status'  => 'success',
                'message' => 'Importación de estudiantes completada.',
            ], 200);

        } catch (ExcelValidationException $e) {
            Log::error('Error de validación al importar estudiantes: ' . $e->getMessage(), [
                'errors' => $e->errors(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Datos inválidos en el archivo.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al importar estudiantes: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al importar estudiantes: ' . $e->getMessage(),
            ], 500);
        }
    }
}
