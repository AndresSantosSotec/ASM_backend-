<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\InscripcionesImport;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EstudiantesImportController extends Controller
{
    public function uploadExcel(Request $request)
    {
        $request->validate([
            'file'    => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'confirm' => 'sometimes|boolean',
            'skip_errors' => 'sometimes|boolean',
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Generar un ID único para esta importación
        $importId = Str::uuid();
        $file = $request->file('file');
        $filename = $file->getClientOriginalName();

        try {
            DB::beginTransaction();

            // 📝 Log de inicio con más detalles
            Log::info("📂 [Importación $importId] Inicio importación de estudiantes", [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'file' => $filename,
                'file_size' => $file->getSize(),
            ]);

            $import = new InscripcionesImport();

            // Configurar el import ID si el método existe
            if (method_exists($import, 'setImportId')) {
                $import->setImportId($importId);
            }

            // Configurar opciones de skip errors si el método existe
            if ($request->boolean('skip_errors') && method_exists($import, 'skipErrors')) {
                $import->skipErrors();
            }

            Excel::import($import, $file);

            // Obtener información de la importación
            $failures = $import->failures();
            $failuresCount = $failures->count();

            // Obtener errores de fila si el método existe, sino usar array vacío
            $rowErrors = method_exists($import, 'getRowErrors') ? $import->getRowErrors() : [];
            $rowErrorsCount = count($rowErrors);

            // Calcular filas exitosas
            // Si no existe getRowCount, estimamos basado en failures
            $totalRows = method_exists($import, 'getRowCount') ? $import->getRowCount() : ($failuresCount + count($rowErrors));
            $successCount = $totalRows - $failuresCount - $rowErrorsCount;

            // 📝 Log de fin con estadísticas
            Log::info("✅ [Importación $importId] Importación finalizada", [
                'total_rows' => $totalRows,
                'success_count' => $successCount,
                'failures_count' => $failuresCount,
                'row_errors_count' => $rowErrorsCount,
            ]);

            // Si hay errores pero el usuario confirmó continuar
            if (($failures->isNotEmpty() || !empty($rowErrors)) && $request->boolean('confirm')) {
                Log::warning("⚠️ [Importación $importId] Importación completada con advertencias confirmadas por el usuario", [
                    'failures' => $failuresCount,
                    'row_errors' => $rowErrorsCount,
                ]);

                DB::commit();

                return response()->json([
                    'status' => 'partial_success',
                    'message' => 'Importación completada con algunos errores.',
                    'import_id' => $importId,
                    'statistics' => [
                        'total_rows' => $totalRows,
                        'successful' => $successCount,
                        'failed' => $failuresCount + $rowErrorsCount,
                    ],
                    'details' => [
                        'failures' => $failures->map(fn($f) => [
                            'row' => $f->row(),
                            'attribute' => $f->attribute(),
                            'errors' => $f->errors(),
                            'values' => $f->values(),
                        ]),
                        'row_errors' => $rowErrors,
                    ],
                ], 207);
            }

            // Si hay errores y no se confirmó continuar
            if ($failures->isNotEmpty() || !empty($rowErrors)) {
                Log::warning("⚠️ [Importación $importId] Importación con errores no confirmados", [
                    'failures' => $failuresCount,
                    'row_errors' => $rowErrorsCount,
                ]);

                DB::rollBack();

                return response()->json([
                    'status' => 'validation_error',
                    'message' => 'Se encontraron errores en el archivo. Revise los detalles.',
                    'import_id' => $importId,
                    'requires_confirmation' => true,
                    'error_summary' => [
                        'total_errors' => $failuresCount + $rowErrorsCount,
                        'validation_errors' => $failuresCount,
                        'processing_errors' => $rowErrorsCount,
                    ],
                    'sample_errors' => array_merge(
                        $failures->take(3)->map(fn($f) => [
                            'row' => $f->row(),
                            'attribute' => $f->attribute(),
                            'errors' => $f->errors(),
                        ])->toArray(),
                        array_slice($rowErrors, 0, 3)
                    ),
                ], 422);
            }

            DB::commit();

            Log::info("🎉 [Importación $importId] Importación completada con éxito", [
                'rows_processed' => $totalRows,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Importación completada exitosamente.',
                'import_id' => $importId,
                'statistics' => [
                    'total_rows' => $totalRows,
                ],
            ], 200);

        } catch (ExcelValidationException $e) {
            DB::rollBack();

            Log::error("❌ [Importación $importId] Error de validación al importar estudiantes", [
                'file' => $filename,
                'errors' => $e->errors(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'status' => 'validation_error',
                'message' => 'El archivo contiene datos inválidos.',
                'import_id' => $importId,
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error("❌ [Importación $importId] Error crítico al importar estudiantes", [
                'file' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error inesperado durante la importación.',
                'import_id' => $importId,
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
