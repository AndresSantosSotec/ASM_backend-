<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PaymentGatewayController extends Controller
{
    /**
     * Listar todas las pasarelas de pago
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaymentGateway::query();

        // Filtro por estado activo
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        // Búsqueda por nombre
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $gateways = $query->orderBy('name')->get();

        return response()->json($gateways->map(function ($gateway) {
            return [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'description' => $gateway->description,
                'commission_percentage' => $gateway->commission_percentage,
                'api_key' => $gateway->api_key ? '****' . substr($gateway->api_key, -4) : null,
                'merchant_id' => $gateway->merchant_id,
                'active' => $gateway->active,
                'is_configured' => $gateway->isFullyConfigured(),
                'created_at' => $gateway->created_at,
                'updated_at' => $gateway->updated_at,
            ];
        }));
    }

    /**
     * Crear nueva pasarela de pago
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:payment_gateways,name',
            'description' => 'nullable|string|max:1000',
            'commission_percentage' => 'required|numeric|min:0|max:100',
            'api_key' => 'nullable|string|max:500',
            'merchant_id' => 'nullable|string|max:255',
            'active' => 'boolean',
            'configuration' => 'nullable|array',
        ]);

        $gateway = PaymentGateway::create($validated);

        return response()->json([
            'message' => 'Pasarela de pago creada exitosamente',
            'data' => $gateway
        ], 201);
    }

    /**
     * Mostrar una pasarela específica
     */
    public function show(PaymentGateway $gateway): JsonResponse
    {
        return response()->json([
            'id' => $gateway->id,
            'name' => $gateway->name,
            'description' => $gateway->description,
            'commission_percentage' => $gateway->commission_percentage,
            'api_key' => $gateway->api_key,
            'merchant_id' => $gateway->merchant_id,
            'active' => $gateway->active,
            'configuration' => $gateway->configuration,
            'is_configured' => $gateway->isFullyConfigured(),
            'created_at' => $gateway->created_at,
            'updated_at' => $gateway->updated_at,
        ]);
    }

    /**
     * Actualizar pasarela de pago
     */
    public function update(Request $request, PaymentGateway $gateway): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:payment_gateways,name,' . $gateway->id,
            'description' => 'nullable|string|max:1000',
            'commission_percentage' => 'sometimes|required|numeric|min:0|max:100',
            'api_key' => 'nullable|string|max:500',
            'merchant_id' => 'nullable|string|max:255',
            'active' => 'boolean',
            'configuration' => 'nullable|array',
        ]);

        $gateway->update($validated);

        return response()->json([
            'message' => 'Pasarela de pago actualizada exitosamente',
            'data' => $gateway
        ]);
    }

    /**
     * Eliminar pasarela de pago
     */
    public function destroy(PaymentGateway $gateway): JsonResponse
    {
        // Verificar que no esté siendo usada (opcional)
        // if ($gateway->payments()->exists()) {
        //     throw ValidationException::withMessages([
        //         'gateway' => ['No se puede eliminar una pasarela que tiene pagos asociados.']
        //     ]);
        // }

        $gateway->delete();

        return response()->json([
            'message' => 'Pasarela de pago eliminada exitosamente'
        ]);
    }

    /**
     * Activar/desactivar pasarela
     */
    public function toggleStatus(PaymentGateway $gateway): JsonResponse
    {
        $gateway->update(['active' => !$gateway->active]);

        return response()->json([
            'message' => $gateway->active ? 'Pasarela activada' : 'Pasarela desactivada',
            'active' => $gateway->active
        ]);
    }

    /**
     * Obtener solo pasarelas activas (para uso público)
     */
    public function activeGateways(): JsonResponse
    {
        $gateways = PaymentGateway::active()
            ->select('id', 'name', 'description', 'commission_percentage')
            ->get();

        return response()->json($gateways);
    }
}
