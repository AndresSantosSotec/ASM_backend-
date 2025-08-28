<?php

namespace App\Http\Controllers\Api;

use App\Models\PaymentRule;
use App\Models\PaymentRuleBlockingRule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;

class PaymentRuleBlockingRuleController extends Controller
{
    /**
     * Listar todas las reglas de bloqueo de una regla de pago
     */
    public function index(PaymentRule $rule): JsonResponse
    {
        $blockingRules = $rule->blockingRules()
            ->orderBy('days_after_due')
            ->get()
            ->map(function ($blockingRule) {
                return [
                    'id' => $blockingRule->id,
                    'name' => $blockingRule->name,
                    'description' => $blockingRule->description,
                    'days_after_due' => $blockingRule->days_after_due,
                    'affected_services' => $blockingRule->affected_services,
                    'readable_services' => $blockingRule->readable_services,
                    'active' => $blockingRule->active,
                    'created_at' => $blockingRule->created_at,
                    'updated_at' => $blockingRule->updated_at,
                ];
            });

        return response()->json($blockingRules);
    }

    /**
     * Crear una nueva regla de bloqueo
     */
    public function store(Request $request, PaymentRule $rule): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'days_after_due' => 'required|integer|min:1|max:365',
            'affected_services' => 'required|array|min:1',
            'affected_services.*' => 'string|in:plataforma,evaluaciones,materiales',
            'active' => 'boolean',
        ]);

        // Verificar que no exista otra regla con los mismos días para la misma regla de pago
        $existingRule = $rule->blockingRules()
            ->where('days_after_due', $validated['days_after_due'])
            ->first();

        if ($existingRule) {
            throw ValidationException::withMessages([
                'days_after_due' => ['Ya existe una regla de bloqueo para estos días después del vencimiento.']
            ]);
        }

        $blockingRule = $rule->blockingRules()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'days_after_due' => $validated['days_after_due'],
            'affected_services' => $validated['affected_services'],
            'active' => $validated['active'] ?? true,
        ]);

        return response()->json([
            'id' => $blockingRule->id,
            'name' => $blockingRule->name,
            'description' => $blockingRule->description,
            'days_after_due' => $blockingRule->days_after_due,
            'affected_services' => $blockingRule->affected_services,
            'readable_services' => $blockingRule->readable_services,
            'active' => $blockingRule->active,
            'created_at' => $blockingRule->created_at,
            'updated_at' => $blockingRule->updated_at,
        ], 201);
    }

    /**
     * Mostrar una regla de bloqueo específica
     */
    public function show(PaymentRule $rule, PaymentRuleBlockingRule $blockingRule): JsonResponse
    {
        // Verificar que la regla de bloqueo pertenece a la regla de pago
        if ($blockingRule->payment_rule_id !== $rule->id) {
            return response()->json(['message' => 'Regla de bloqueo no encontrada.'], 404);
        }

        return response()->json([
            'id' => $blockingRule->id,
            'name' => $blockingRule->name,
            'description' => $blockingRule->description,
            'days_after_due' => $blockingRule->days_after_due,
            'affected_services' => $blockingRule->affected_services,
            'readable_services' => $blockingRule->readable_services,
            'active' => $blockingRule->active,
            'created_at' => $blockingRule->created_at,
            'updated_at' => $blockingRule->updated_at,
        ]);
    }

    /**
     * Actualizar una regla de bloqueo
     */
    public function update(Request $request, PaymentRule $rule, PaymentRuleBlockingRule $blockingRule): JsonResponse
    {
        // Verificar que la regla de bloqueo pertenece a la regla de pago
        if ($blockingRule->payment_rule_id !== $rule->id) {
            return response()->json(['message' => 'Regla de bloqueo no encontrada.'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'days_after_due' => 'sometimes|required|integer|min:1|max:365',
            'affected_services' => 'sometimes|required|array|min:1',
            'affected_services.*' => 'string|in:plataforma,evaluaciones,materiales',
            'active' => 'boolean',
        ]);

        // Si se está actualizando los días, verificar que no exista conflicto
        if (isset($validated['days_after_due']) && $validated['days_after_due'] !== $blockingRule->days_after_due) {
            $existingRule = $rule->blockingRules()
                ->where('days_after_due', $validated['days_after_due'])
                ->where('id', '!=', $blockingRule->id)
                ->first();

            if ($existingRule) {
                throw ValidationException::withMessages([
                    'days_after_due' => ['Ya existe una regla de bloqueo para estos días después del vencimiento.']
                ]);
            }
        }

        $blockingRule->update($validated);

        return response()->json([
            'id' => $blockingRule->id,
            'name' => $blockingRule->name,
            'description' => $blockingRule->description,
            'days_after_due' => $blockingRule->days_after_due,
            'affected_services' => $blockingRule->affected_services,
            'readable_services' => $blockingRule->readable_services,
            'active' => $blockingRule->active,
            'created_at' => $blockingRule->created_at,
            'updated_at' => $blockingRule->updated_at,
        ]);
    }

    /**
     * Eliminar una regla de bloqueo
     */
    public function destroy(PaymentRule $rule, PaymentRuleBlockingRule $blockingRule): JsonResponse
    {
        // Verificar que la regla de bloqueo pertenece a la regla de pago
        if ($blockingRule->payment_rule_id !== $rule->id) {
            return response()->json(['message' => 'Regla de bloqueo no encontrada.'], 404);
        }

        $blockingRule->delete();

        return response()->json(['message' => 'Regla de bloqueo eliminada correctamente.']);
    }

    /**
     * Activar/desactivar una regla de bloqueo
     */
    public function toggleStatus(PaymentRule $rule, PaymentRuleBlockingRule $blockingRule): JsonResponse
    {
        // Verificar que la regla de bloqueo pertenece a la regla de pago
        if ($blockingRule->payment_rule_id !== $rule->id) {
            return response()->json(['message' => 'Regla de bloqueo no encontrada.'], 404);
        }

        $blockingRule->update(['active' => !$blockingRule->active]);

        return response()->json([
            'id' => $blockingRule->id,
            'active' => $blockingRule->active,
            'message' => $blockingRule->active ? 'Regla activada.' : 'Regla desactivada.'
        ]);
    }

    /**
     * Obtener reglas aplicables para ciertos días de atraso
     */
    public function getApplicableRules(Request $request, PaymentRule $rule): JsonResponse
    {
        $validated = $request->validate([
            'days_overdue' => 'required|integer|min:0',
            'services' => 'sometimes|array',
            'services.*' => 'string|in:plataforma,evaluaciones,materiales',
        ]);

        $daysOverdue = $validated['days_overdue'];
        $services = $validated['services'] ?? null;

        $applicableRules = $rule->activeBlockingRules()
            ->where('days_after_due', '<=', $daysOverdue)
            ->get()
            ->filter(function ($blockingRule) use ($services) {
                if ($services === null) {
                    return true;
                }

                // Verificar si algún servicio está afectado
                return collect($services)->some(function ($service) use ($blockingRule) {
                    return $blockingRule->affectsService($service);
                });
            })
            ->map(function ($blockingRule) {
                return [
                    'id' => $blockingRule->id,
                    'name' => $blockingRule->name,
                    'description' => $blockingRule->description,
                    'days_after_due' => $blockingRule->days_after_due,
                    'affected_services' => $blockingRule->affected_services,
                    'readable_services' => $blockingRule->readable_services,
                ];
            });

        return response()->json($applicableRules);
    }
}
