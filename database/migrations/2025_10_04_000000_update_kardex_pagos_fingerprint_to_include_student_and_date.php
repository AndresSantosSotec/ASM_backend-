<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Updates existing kardex_pagos records to use new fingerprint format
     * that includes estudiante_programa_id and fecha_pago for better uniqueness.
     * 
     * Old format: hash(banco_normalizado | numero_boleta_normalizada)
     * New format: hash(banco_normalizado | numero_boleta_normalizada | estudiante_programa_id | fecha_pago)
     */
    public function up(): void
    {
        // Temporarily drop the unique constraint to allow updates
        Schema::table('kardex_pagos', function (Blueprint $table) {
            $table->dropUnique(['boleta_fingerprint']);
        });

        // Update all existing records with new fingerprint format
        // Process in chunks to avoid memory issues
        $updated = 0;
        $chunkSize = 500;
        
        DB::table('kardex_pagos')->orderBy('id')->chunk($chunkSize, function ($pagos) use (&$updated) {
            foreach ($pagos as $pago) {
                // Skip if missing required fields
                if (empty($pago->banco_normalizado) || empty($pago->numero_boleta_normalizada)) {
                    continue;
                }
                
                // Extract date from fecha_pago
                $fecha = 'UNKNOWN';
                if (!empty($pago->fecha_pago)) {
                    try {
                        $fecha = date('Y-m-d', strtotime($pago->fecha_pago));
                    } catch (\Exception $e) {
                        // Keep UNKNOWN if date parsing fails
                    }
                }
                
                // Calculate new fingerprint
                $estudiante = $pago->estudiante_programa_id ?? 'UNKNOWN';
                $newFingerprint = hash('sha256', 
                    $pago->banco_normalizado . '|' . 
                    $pago->numero_boleta_normalizada . '|' . 
                    $estudiante . '|' . 
                    $fecha
                );
                
                // Update only if fingerprint changed
                if ($pago->boleta_fingerprint !== $newFingerprint) {
                    DB::table('kardex_pagos')
                        ->where('id', $pago->id)
                        ->update(['boleta_fingerprint' => $newFingerprint]);
                    $updated++;
                }
            }
        });

        // Re-add the unique constraint
        Schema::table('kardex_pagos', function (Blueprint $table) {
            $table->unique('boleta_fingerprint');
        });

        // Log the update
        \Log::info("Updated {$updated} kardex_pagos records with new fingerprint format");
    }

    /**
     * Reverse the migrations.
     * 
     * Note: This cannot fully reverse the fingerprint changes since we can't 
     * determine which records had duplicate fingerprints before.
     * This migration should not be rolled back in production.
     */
    public function down(): void
    {
        // Drop unique constraint
        Schema::table('kardex_pagos', function (Blueprint $table) {
            $table->dropUnique(['boleta_fingerprint']);
        });

        // Recalculate fingerprints with old format (banco + boleta only)
        DB::table('kardex_pagos')->orderBy('id')->chunk(500, function ($pagos) {
            foreach ($pagos as $pago) {
                if (empty($pago->banco_normalizado) || empty($pago->numero_boleta_normalizada)) {
                    continue;
                }
                
                $oldFingerprint = hash('sha256', 
                    $pago->banco_normalizado . '|' . 
                    $pago->numero_boleta_normalizada
                );
                
                DB::table('kardex_pagos')
                    ->where('id', $pago->id)
                    ->update(['boleta_fingerprint' => $oldFingerprint]);
            }
        });

        // Re-add unique constraint (this may fail if there are duplicates)
        try {
            Schema::table('kardex_pagos', function (Blueprint $table) {
                $table->unique('boleta_fingerprint');
            });
        } catch (\Exception $e) {
            \Log::warning("Could not re-add unique constraint on boleta_fingerprint: " . $e->getMessage());
        }
    }
};
