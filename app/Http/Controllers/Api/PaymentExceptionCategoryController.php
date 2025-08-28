<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentExceptionCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentExceptionCategoryController extends Controller
{
    /**
     * Listar todas las categorías de excepción
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaymentExceptionCategory::query();

        // Filtro por estado activo
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        // Búsqueda por nombre
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $categories = $query->orderBy('name')->get();

        return response()->json($categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'due_day_override' => $category->due_day_override,
                'skip_late_fee' => $category->skip_late_fee,
                'allow_partial_payments' => $category->allow_partial_payments,
                'skip_blocking' => $category->skip_blocking,
                'active' => $category->active,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ];
        }));
    }

    /**
     * Crear nueva categoría de excepción
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:payment_exception_categories,name',
            'description' => 'nullable|string|max:1000',
            'due_day_override' => 'nullable|integer|min:1|max:28',
            'skip_late_fee' => 'boolean',
            'allow_partial_payments' => 'boolean',
            'skip_blocking' => 'boolean',
            'active' => 'boolean',
            'additional_rules' => 'nullable|array',
        ]);

        $category = PaymentExceptionCategory::create($validated);

        return response()->json([
            'message' => 'Categoría de excepción creada exitosamente',
            'data' => $category
        ], 201);
    }

    /**
     * Mostrar una categoría específica
     */
    public function show(PaymentExceptionCategory $category): JsonResponse
    {
        return response()->json([
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'due_day_override' => $category->due_day_override,
            'skip_late_fee' => $category->skip_late_fee,
            'allow_partial_payments' => $category->allow_partial_payments,
            'skip_blocking' => $category->skip_blocking,
            'additional_rules' => $category->additional_rules,
            'active' => $category->active,
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
        ]);
    }

    /**
     * Actualizar categoría de excepción
     */
    public function update(Request $request, PaymentExceptionCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:payment_exception_categories,name,' . $category->id,
            'description' => 'nullable|string|max:1000',
            'due_day_override' => 'nullable|integer|min:1|max:28',
            'skip_late_fee' => 'boolean',
            'allow_partial_payments' => 'boolean',
            'skip_blocking' => 'boolean',
            'active' => 'boolean',
            'additional_rules' => 'nullable|array',
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'Categoría de excepción actualizada exitosamente',
            'data' => $category
        ]);
    }

    /**
     * Eliminar categoría de excepción
     */
    public function destroy(PaymentExceptionCategory $category): JsonResponse
    {
        // Verificar que no esté siendo usada
        if ($category->prospectos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar una categoría que tiene prospectos asignados.'
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Categoría de excepción eliminada exitosamente'
        ]);
    }

    /**
     * Activar/desactivar categoría
     */
    public function toggleStatus(PaymentExceptionCategory $category): JsonResponse
    {
        $category->update(['active' => !$category->active]);

        return response()->json([
            'message' => $category->active ? 'Categoría activada' : 'Categoría desactivada',
            'active' => $category->active
        ]);
    }

    /**
     * Asignar categoría a un prospecto/estudiante
     */
    public function assignToProspecto(Request $request, PaymentExceptionCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'prospecto_id' => 'required|exists:prospectos,id',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after:effective_from',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Verificar si ya existe una asignación activa
        $existingAssignment = $category->prospectos()
            ->where('prospecto_id', $validated['prospecto_id'])
            ->wherePivot(function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('effective_until')
                      ->orWhere('effective_until', '>=', now());
                });
            })
            ->exists();

        if ($existingAssignment) {
            return response()->json([
                'message' => 'El prospecto ya tiene esta categoría asignada activamente'
            ], 422);
        }

        $category->prospectos()->attach($validated['prospecto_id'], [
            'effective_from' => $validated['effective_from'] ?? null,
            'effective_until' => $validated['effective_until'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Categoría asignada al prospecto exitosamente'
        ]);
    }

    /**
     * Remover categoría de un prospecto
     */
    public function removeFromProspecto(Request $request, PaymentExceptionCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'prospecto_id' => 'required|exists:prospectos,id',
        ]);

        $category->prospectos()->detach($validated['prospecto_id']);

        return response()->json([
            'message' => 'Categoría removida del prospecto exitosamente'
        ]);
    }

    /**
     * Listar prospectos asignados a una categoría
     */
    public function assignedProspectos(PaymentExceptionCategory $category): JsonResponse
    {
        $prospectos = $category->prospectos()
            ->withPivot('effective_from', 'effective_until', 'notes')
            ->get()
            ->map(function ($prospecto) {
                return [
                    'id' => $prospecto->id,
                    'nombre_completo' => $prospecto->nombre_completo,
                    'carnet' => $prospecto->carnet,
                    'correo_electronico' => $prospecto->correo_electronico,
                    'assignment' => [
                        'effective_from' => $prospecto->pivot->effective_from,
                        'effective_until' => $prospecto->pivot->effective_until,
                        'notes' => $prospecto->pivot->notes,
                        'is_active' => $this->isAssignmentActive($prospecto->pivot),
                    ]
                ];
            });

        return response()->json($prospectos);
    }

    /**
     * Verifica si una asignación está activa
     */
    private function isAssignmentActive($pivot): bool
    {
        $now = now();

        $validFrom = !$pivot->effective_from || $pivot->effective_from <= $now;
        $validUntil = !$pivot->effective_until || $pivot->effective_until >= $now;

        return $validFrom && $validUntil;
    }

    /**
     * MÉTODO LEGACY: Asignar categoría a un estudiante (mantener por compatibilidad)
     * @deprecated Usar assignToProspecto en su lugar
     */
    public function assignToStudent(Request $request, PaymentExceptionCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:users,id', // o students table
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after:effective_from',
        ]);

        // Para mantener compatibilidad, podrías mapear student_id a prospecto_id
        // o mantener esta funcionalidad si manejas ambos tipos de usuarios

        return response()->json([
            'message' => 'Método deprecado, usar assignToProspecto en su lugar'
        ], 410);
    }
}
