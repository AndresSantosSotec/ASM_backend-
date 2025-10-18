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

        $importId = Str::uuid();
        $file = $request->file('file');
        $filename = $file->getClientOriginalName();

        try {
            DB::beginTransaction();

            Log::info("📂 [Importación $importId] Inicio importación de estudiantes", [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'file' => $filename,
                'file_size' => $file->getSize(),
            ]);

            $import = new InscripcionesImport();

            if (method_exists($import, 'setImportId')) {
                $import->setImportId($importId);
            }

            if ($request->boolean('skip_errors') && method_exists($import, 'skipErrors')) {
                $import->skipErrors();
            }

            // Ejecutar importación
            Excel::import($import, $file);

            // Recolectar resultados
            $failures = $import->failures();
            $rowErrors = method_exists($import, 'getRowErrors') ? $import->getRowErrors() : [];
            $totalRows = method_exists($import, 'getRowCount') ? $import->getRowCount() : 0;

            $failuresCount = $failures->count();
            $rowErrorsCount = count($rowErrors);
            $successCount = $totalRows - $failuresCount - $rowErrorsCount;

            // 🔍 Analizar causas comunes de error
            $programErrors = [];
            foreach ($rowErrors as $err) {
                if (str_contains(strtolower($err['error']), 'programa') || str_contains(strtolower($err['error']), 'no encontrado')) {
                    $programErrors[] = $err;
                }
            }

            // 🧮 Resumen final de estadísticas
            $summary = [
                'total_rows' => $totalRows,
                'successful' => max($successCount, 0),
                'failed' => $failuresCount + $rowErrorsCount,
                'validation_errors' => $failuresCount,
                'processing_errors' => $rowErrorsCount,
                'unknown_programs' => count($programErrors),
            ];

            // 📝 Crear resumen textual de log
            $logSummary = [
                '✅ Registros exitosos' => $summary['successful'],
                '⚠️ Errores de validación' => $summary['validation_errors'],
                '❌ Errores de procesamiento' => $summary['processing_errors'],
                '🚫 Programas no encontrados' => $summary['unknown_programs'],
            ];

            Log::info("📊 [Importación $importId] Resumen de resultados", $logSummary);

            // Si hay errores
            if ($summary['failed'] > 0) {
                DB::commit(); // permitimos guardar lo que sí se insertó

                return response()->json([
                    'status' => 'partial_success',
                    'message' => 'Importación completada con errores en algunos registros.',
                    'import_id' => $importId,
                    'summary' => $summary,
                    'details' => [
                        'failures' => $failures->map(fn($f) => [
                            'row' => $f->row(),
                            'attribute' => $f->attribute(),
                            'errors' => $f->errors(),
                            'values' => $f->values(),
                        ]),
                        'row_errors' => $rowErrors,
                        'program_errors' => $programErrors,
                    ],
                ], 207);
            }

            // Si todo fue exitoso
            DB::commit();

            Log::info("🎉 [Importación $importId] Importación completada con éxito", [
                'rows_processed' => $totalRows,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Importación completada exitosamente.',
                'import_id' => $importId,
                'summary' => $summary,
            ], 200);

        } catch (ExcelValidationException $e) {
            DB::rollBack();

            Log::error("❌ [Importación $importId] Error de validación", [
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

            Log::error("❌ [Importación $importId] Error crítico", [
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
