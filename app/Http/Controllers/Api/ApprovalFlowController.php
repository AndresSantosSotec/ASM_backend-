<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalFlow;
use App\Models\ApprovalStage;
use App\Models\Programa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ApprovalFlowController extends Controller
{
    /* ========== GET /approval-flows ========== */
    public function index()
    {
        $flows = ApprovalFlow::with('programs:id,abreviatura,nombre_del_programa')
                 ->withCount('stages')
                 ->get()
                 ->map(fn ($f) => [
                     'id'        => $f->id,
                     'nombre'    => $f->name,
                     'aplicable' => $f->scope === 'custom'
                         ? $f->programs->pluck('abreviatura')->implode(', ')
                         : ucfirst($f->scope),
                     'etapas'    => $f->stages_count,
                     'estado'    => $f->is_active ? 'Activo' : 'Inactivo',
                     'tiempo'    => $f->avg_hours . ' horas',
                 ]);

        return response()->json($flows);
    }

    /* ========== POST /approval-flows ========== */
    public function store(Request $request)
    {
        $data = $this->validateFlow($request);

        DB::transaction(function () use ($data, &$flow) {
            $flow = ApprovalFlow::create($data['flow']);

            // Si es scope custom guarda programas específicos
            if ($flow->scope === 'custom' && !empty($data['program_ids'])) {
                $flow->programs()->sync($data['program_ids']);
            }

            // etapas
            if (!empty($data['stages'])) {
                foreach ($data['stages'] as $idx => $stage) {
                    $flow->stages()->create(array_merge($stage, [
                        'stage_order' => $idx + 1,
                    ]));
                }
            }
        });

        return response()->json([
            'message' => 'Flujo creado',
            'id'      => $flow->id,
        ], 201);
    }

    /* ========== GET /approval-flows/{flow} ========== */
    public function show(ApprovalFlow $flow)
    {
        return $flow->load('stages', 'programs:id,abreviatura');
    }

    /* ========== PUT /approval-flows/{flow} ========== */
    public function update(Request $request, ApprovalFlow $flow)
    {
        $data = $this->validateFlow($request, $flow->id);

        DB::transaction(function () use ($data, $flow) {
            $flow->update($data['flow']);

            // programas
            if ($flow->scope === 'custom') {
                $flow->programs()->sync($data['program_ids'] ?? []);
            } else {
                $flow->programs()->detach();
            }

            // etapas (estrategia simple: eliminar y recrear)
            if (isset($data['stages'])) {
                $flow->stages()->delete();
                foreach ($data['stages'] as $idx => $stage) {
                    $flow->stages()->create(array_merge($stage, [
                        'stage_order' => $idx + 1,
                    ]));
                }
            }
        });

        return response()->json(['message' => 'Flujo actualizado']);
    }

    /* ========== DELETE /approval-flows/{flow} ========== */
    public function destroy(ApprovalFlow $flow)
    {
        $flow->delete();
        return response()->json(['message' => 'Eliminado']);
    }

    /* ========== POST /approval-flows/{flow}/toggle ========== */
    public function toggle(ApprovalFlow $flow)
    {
        $flow->toggle();
        return response()->json([
            'estado' => $flow->is_active ? 'Activo' : 'Inactivo',
        ]);
    }

    /* ========== Helpers ========== */
    private function validateFlow(Request $request, ?int $id = null): array
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:120',
            'code'           => ['required','string','max:40', Rule::unique('approval_flows','code')->ignore($id)],
            'description'    => 'nullable|string',
            'scope'          => 'required|in:todos,maestrias,diplomados,cursos,custom',
            'is_active'      => 'boolean',
            'parallel'       => 'boolean',
            'auto_escalate'  => 'boolean',

            'program_ids'    => 'array',
            'program_ids.*'  => 'exists:tb_programas,id',

            'stages'                     => 'array',
            'stages.*.title'             => 'required|string|max:120',
            'stages.*.approver_role_id'  => 'nullable|integer',
            'stages.*.approver_role_slug'=> 'nullable|string|max:60',
            'stages.*.max_hours'         => 'integer|min:1',
            'stages.*.mandatory'         => 'boolean',
            'stages.*.notify_requester'  => 'boolean',
        ]);

        return [
            'flow'        => collect($validated)->only([
                                'name','code','description','scope',
                                'is_active','parallel','auto_escalate'
                             ])->toArray(),
            'program_ids' => $validated['program_ids']  ?? [],
            'stages'      => $validated['stages']       ?? [],
        ];
    }
}
