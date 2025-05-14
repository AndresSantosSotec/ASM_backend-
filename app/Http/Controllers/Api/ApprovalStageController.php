<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalFlow;
use App\Models\ApprovalStage;
use Illuminate\Http\Request;

class ApprovalStageController extends Controller
{
    /* ========== POST /approval-flows/{flow}/stages ========== */
    public function store(Request $request, ApprovalFlow $flow)
    {
        $data = $this->validateStage($request);

        // stage_order = último + 1
        $data['stage_order'] = ($flow->stages()->max('stage_order') ?? 0) + 1;
        $stage = $flow->stages()->create($data);

        return response()->json(['id' => $stage->id], 201);
    }

    /* ========== PUT /approval-flows/stages/{stage} ========== */
    public function update(Request $request, ApprovalStage $stage)
    {
        $stage->update($this->validateStage($request));
        return response()->json(['message' => 'Etapa actualizada']);
    }

    /* ========== DELETE /approval-flows/stages/{stage} ========== */
    public function destroy(ApprovalStage $stage)
    {
        $stage->delete();
        return response()->json(['message' => 'Etapa eliminada']);
    }

    /* ========== Helpers ========== */
    private function validateStage(Request $request): array
    {
        return $request->validate([
            'title'              => 'required|string|max:120',
            'approver_role_id'   => 'nullable|integer',
            'approver_role_slug' => 'nullable|string|max:60',
            'max_hours'          => 'integer|min:1',
            'mandatory'          => 'boolean',
            'notify_requester'   => 'boolean',
        ]);
    }
}
