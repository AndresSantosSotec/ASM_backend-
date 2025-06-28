<?php
// app/Http/Controllers/Api/DuplicateRecordController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DuplicateRecord;
use App\Models\Prospecto;
use Illuminate\Http\Request;

class DuplicateRecordController extends Controller
{
    /**
     * GET /api/duplicates
     * Lista todos los duplicados, con paginación y filtros opcionales.
     */
    public function index(Request $request)
    {
        // 1) Eager-load de las relaciones
        $query = DuplicateRecord::with(['originalProspect', 'duplicateProspect']);

        // 2) Filtros opcionales
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('min_score')) {
            $query->where('similarity_score', '>=', (int)$request->min_score);
        }

        // 3) Paginación y orden descendente por similitud
        $perPage = (int)$request->get('per_page', 15);
        $duplicates = $query
            ->orderBy('similarity_score', 'desc')
            ->paginate($perPage);

        // 4) Retornar paginador completo (incluye data, meta, links…)
        return response()->json($duplicates);
    }

    /**
     * GET /api/prospectos/{id}/duplicates
     * Duplicados que involucran a un prospecto concreto.
     */
    public function forProspecto(Request $request, $id)
    {
        $query = DuplicateRecord::with(['originalProspect', 'duplicateProspect'])
            ->where('original_prospect_id', $id)
            ->orWhere('duplicate_prospect_id', $id);

        if ($request->filled('min_score')) {
            $query->where('similarity_score', '>=', (int)$request->min_score);
        }

        $dups = $query
            ->orderBy('similarity_score', 'desc')
            ->get();

        return response()->json([
            'data' => $dups
        ], 200);
    }

    /**
     * POST /api/duplicates/detect
     * (Botón “Buscar duplicados”) Vacía la tabla y vuelve a detectar.
     */
    public function detect(Request $request)
    {
        // 1) Vaciar tabla
        DuplicateRecord::truncate();

        // 2) Chunk para no saturar memoria
        Prospecto::select('id', 'nombre_completo', 'correo_electronico', 'telefono')
            ->orderBy('id')
            ->chunkById(200, function ($prospects) {
                $arr   = $prospects->all();
                $count = count($arr);

                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        $p1 = $arr[$i];
                        $p2 = $arr[$j];

                        // Calcular similitud
                        similar_text($p1->nombre_completo, $p2->nombre_completo, $nameSim);
                        similar_text($p1->correo_electronico, $p2->correo_electronico, $emailSim);
                        $phoneSim = ($p1->telefono === $p2->telefono) ? 100 : 0;

                        // Ponderar y umbral
                        $score = round($nameSim * 0.5 + $emailSim * 0.3 + $phoneSim * 0.2);
                        if ($score >= 80) {
                            DuplicateRecord::create([
                                'original_prospect_id'  => $p1->id,
                                'duplicate_prospect_id' => $p2->id,
                                'similarity_score'      => $score,
                                'status'                => 'pending',
                            ]);
                        }
                    }
                }
            });

        return response()->json(['message' => 'Detección completada'], 200);
    }

    /**
     * POST /api/duplicates/{id}/action
     * Ejecuta la acción seleccionada sobre un duplicado.
     */
    public function action(Request $request, $id)
    {
        $dup = DuplicateRecord::findOrFail($id);

        $request->validate([
            'action' => 'required|in:keep_original,keep_duplicate,delete_duplicate,mark_reviewed'
        ]);

        switch ($request->action) {
            case 'keep_original':
                Prospecto::findOrFail($dup->duplicate_prospect_id)->delete();
                $dup->status = 'resolved';
                break;

            case 'keep_duplicate':
                Prospecto::findOrFail($dup->original_prospect_id)->delete();
                $dup->status = 'resolved';
                break;

            case 'delete_duplicate':
                Prospecto::findOrFail($dup->duplicate_prospect_id)->delete();
                $dup->status = 'resolved';
                break;

            case 'mark_reviewed':
                $dup->status = 'resolved';
                break;
        }

        $dup->save();

        return response()->json(['message' => 'Acción ejecutada'], 200);
    }


    public function bulkAction(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:duplicate_records,id',
            'action' => 'required|in:keep_original,keep_duplicate,delete_duplicate,mark_reviewed'
        ]);

        $affected = 0;

        foreach ($request->ids as $id) {
            $dup = DuplicateRecord::find($id);
            if (!$dup) continue;

            switch ($request->action) {
                case 'keep_original':
                case 'delete_duplicate':
                    Prospecto::find($dup->duplicate_prospect_id)?->delete();
                    $dup->status = 'resolved';
                    break;
                case 'keep_duplicate':
                    Prospecto::find($dup->original_prospect_id)?->delete();
                    $dup->status = 'resolved';
                    break;
                case 'mark_reviewed':
                    $dup->status = 'resolved';
                    break;
            }
            $dup->save();
            $affected++;
        }

        return response()->json([
            'message' => "Acción ejecutada sobre $affected duplicados."
        ]);
    }
}
