<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProspectosImport;
use App\Models\Prospecto;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProspectosImportController extends Controller
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
            $file    = $request->file('file');
            $userId  = $user->id;
            $confirm = $request->boolean('confirm', false);

            // 1) Leer filas sin insertar
            $import = new ProspectosImport($userId);
            $sheets = Excel::toCollection($import, $file);
            $rows   = $sheets->first();

            // 2) Construir listas
            $emails = $rows
                ->pluck('correo_electronico')
                ->filter()
                ->map(fn($e) => strtolower(trim($e)))
                ->toArray();

            $existing = Prospecto::whereIn('correo_electronico', $emails)
                ->pluck('correo_electronico')
                ->map(fn($e) => strtolower(trim($e)))
                ->toArray();

            // Cuántos son duplicados vs nuevos
            $filteredRows = $rows->filter(
                fn($r) =>
                !in_array(strtolower(trim($r['correo_electronico'] ?? '')), $existing)
            );
            $newCount = $filteredRows->count();
            $dupCount = count($emails) - $newCount;

            // 3) Si hay duplicados y NO confirmaron → devolver lista
            if ($dupCount > 0 && !$confirm) {
                $dupList = array_count_values(array_intersect($emails, $existing));
                $duplicates = [];
                foreach ($dupList as $email => $cnt) {
                    $duplicates[] = ['correo_electronico' => $email, 'count' => $cnt];
                }
                return response()->json([
                    'status'     => 'duplicates',
                    'duplicates' => $duplicates,
                    'insertable' => $newCount,
                    'skipped'    => $dupCount,
                    'message'    => "Hay {$dupCount} duplicado(s), {$newCount} nuevos.",
                ], 200);
            }

            // 4) Importar todo (incluidos duplicados si confirmaron, o sin duplicados si no los hubo)
            Excel::import(new ProspectosImport($userId), $file);
            $inserted = count($emails);

            // 5) Si no insertó nada
            if ($inserted === 0) {
                return response()->json([
                    'status'  => 'empty',
                    'message' => 'No se insertó ningún prospecto.',
                ], 200);
            }

            // 6) Éxito
            return response()->json([
                'status'   => 'success',
                'inserted' => $inserted,
                'skipped'  => $dupCount,
                'message'  => "Insertados: {$inserted}. Duplicados: {$dupCount}.",
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al importar prospectos: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al importar prospectos: ' . $e->getMessage(),
            ], 500);
        }
    }
}
