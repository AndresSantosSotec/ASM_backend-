<?php

/**
 * EJEMPLO: Cómo usar los modos de reemplazo en un controlador
 * 
 * Este archivo muestra ejemplos de integración en controladores
 * NO es un archivo funcional, solo documentación de ejemplo
 */

namespace App\Http\Controllers\Example;

use App\Imports\PaymentHistoryImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PaymentImportExampleController
{
    /**
     * Importación normal - No modifica cuotas existentes
     */
    public function importNormal(Request $request)
    {
        $file = $request->file('excel');
        $userId = auth()->id();
        
        $import = new PaymentHistoryImport($userId);
        Excel::import($import, $file);
        
        return response()->json([
            'message' => 'Importación completada',
            'procesados' => $import->procesados,
            'kardex_creados' => $import->kardexCreados,
            'errores' => $import->errores
        ]);
    }
    
    /**
     * Reemplazo de pendientes - Actualiza cuotas pendientes a pagadas
     * 
     * Usar cuando:
     * - Se necesita actualizar pagos sobre estructura existente
     * - No se quiere eliminar datos históricos
     * - Se importan pagos recientes sobre cuotas ya creadas
     */
    public function importConReemplazoPendientes(Request $request)
    {
        $file = $request->file('excel');
        $userId = auth()->id();
        
        $import = new PaymentHistoryImport(
            $userId,
            'cardex_directo',
            true  // modoReemplazoPendientes = true
        );
        
        Excel::import($import, $file);
        
        return response()->json([
            'message' => 'Importación con reemplazo de pendientes completada',
            'procesados' => $import->procesados,
            'cuotas_actualizadas' => $import->cuotasActualizadas,
            'errores' => $import->errores
        ]);
    }
    
    /**
     * Reemplazo total - Purga y reconstruye todo
     * 
     * Usar cuando:
     * - Se necesita reimportar datos históricos desde cero
     * - Hay duplicados o inconsistencias en los datos
     * - Se quiere "resetear" completamente un estudiante
     * 
     * ⚠️ ADVERTENCIA: Elimina TODOS los datos existentes del estudiante
     */
    public function importConReemplazoTotal(Request $request)
    {
        $file = $request->file('excel');
        $userId = auth()->id();
        
        // Validar que el usuario tenga permiso para hacer reemplazo total
        if (!auth()->user()->can('purge-payment-data')) {
            return response()->json([
                'error' => 'No tienes permiso para purgar datos'
            ], 403);
        }
        
        $import = new PaymentHistoryImport(
            $userId,
            'cardex_directo',
            false, // modoReemplazoPendientes = false
            true   // modoReemplazo = true
        );
        
        Excel::import($import, $file);
        
        return response()->json([
            'message' => 'Reemplazo total completado',
            'procesados' => $import->procesados,
            'kardex_creados' => $import->kardexCreados,
            'advertencia' => 'Se eliminaron y reconstruyeron todas las cuotas',
            'errores' => $import->errores
        ]);
    }
    
    /**
     * Importación con ambos modos
     * 
     * Útil para:
     * - Corrección masiva de datos con actualización incremental
     * - Migración de sistemas antiguos
     */
    public function importConAmbosModos(Request $request)
    {
        $file = $request->file('excel');
        $userId = auth()->id();
        
        $import = new PaymentHistoryImport(
            $userId,
            'cardex_directo',
            true,  // modoReemplazoPendientes = true
            true   // modoReemplazo = true
        );
        
        Excel::import($import, $file);
        
        return response()->json([
            'message' => 'Importación con ambos modos completada',
            'procesados' => $import->procesados,
            'kardex_creados' => $import->kardexCreados,
            'cuotas_actualizadas' => $import->cuotasActualizadas,
            'errores' => $import->errores,
            'advertencia' => 'Se aplicó purge + rebuild + reemplazo de pendientes'
        ]);
    }
    
    /**
     * Endpoint para verificar cuotas antes de importar
     * 
     * Útil para:
     * - Prevenir antes de hacer un reemplazo total
     * - Verificar qué se va a eliminar
     */
    public function verificarCuotasExistentes(Request $request)
    {
        $carnet = $request->input('carnet');
        
        $prospecto = \DB::table('prospectos')
            ->where(\DB::raw("REPLACE(UPPER(carnet), ' ', '')"), '=', strtoupper(str_replace(' ', '', $carnet)))
            ->first();
            
        if (!$prospecto) {
            return response()->json([
                'message' => 'No se encontró el estudiante',
                'tiene_cuotas' => false
            ]);
        }
        
        $cuotas = \DB::table('cuotas_programa_estudiante as c')
            ->join('estudiante_programa as ep', 'c.estudiante_programa_id', '=', 'ep.id')
            ->where('ep.prospecto_id', $prospecto->id)
            ->select(
                'c.estudiante_programa_id',
                \DB::raw('COUNT(*) as total_cuotas'),
                \DB::raw('SUM(CASE WHEN c.estado = "pendiente" THEN 1 ELSE 0 END) as pendientes'),
                \DB::raw('SUM(CASE WHEN c.estado = "pagado" THEN 1 ELSE 0 END) as pagadas')
            )
            ->groupBy('c.estudiante_programa_id')
            ->get();
            
        $kardex = \DB::table('kardex_pagos as k')
            ->join('estudiante_programa as ep', 'k.estudiante_programa_id', '=', 'ep.id')
            ->where('ep.prospecto_id', $prospecto->id)
            ->count();
            
        return response()->json([
            'message' => 'Verificación completada',
            'carnet' => $carnet,
            'prospecto_id' => $prospecto->id,
            'tiene_cuotas' => $cuotas->isNotEmpty(),
            'detalle_cuotas' => $cuotas,
            'kardex_registrados' => $kardex,
            'recomendacion' => $cuotas->isEmpty() 
                ? 'Usar importación normal' 
                : 'Considerar modo reemplazo de pendientes o reemplazo total'
        ]);
    }
}
