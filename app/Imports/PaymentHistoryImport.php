<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\KardexPago;
use App\Models\CuotaProgramaEstudiante;
use App\Models\ReconciliationRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentHistoryImport implements ToCollection, WithHeadingRow
{
    public int $uploaderId;
    public string $tipoArchivo;
    public int $totalRows = 0;
    public int $procesados = 0;
    public int $kardexCreados = 0;
    public int $cuotasActualizadas = 0;
    public int $conciliaciones = 0;
    public float $totalAmount = 0;
    public array $errores = [];
    public array $advertencias = [];
    public array $detalles = [];

    // 🆕 Estadísticas de discrepancias
    public int $pagosParciales = 0;
    public float $totalDiscrepancias = 0;

    private array $estudiantesCache = [];
    private array $cuotasPorEstudianteCache = [];

    public function __construct(int $uploaderId, string $tipoArchivo = 'cardex_directo')
    {
        $this->uploaderId = $uploaderId;
        $this->tipoArchivo = $tipoArchivo;

        Log::info('📦 PaymentHistoryImport Constructor', [
            'uploaderId' => $uploaderId,
            'tipoArchivo' => $tipoArchivo,
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    public function collection(Collection $rows)
    {
        $this->totalRows = $rows->count();

        Log::info('=== 🚀 INICIANDO PROCESAMIENTO ===', [
            'total_rows' => $this->totalRows,
            'primera_fila' => $rows->first()?->toArray(),
            'columnas_detectadas' => $rows->first() ? array_keys($rows->first()->toArray()) : [],
            'timestamp' => now()->toDateTimeString()
        ]);

        // ✅ Validar que haya datos
        if ($this->totalRows === 0) {
            $this->errores[] = [
                'tipo' => 'ARCHIVO_VACIO',
                'error' => 'El archivo no contiene datos válidos para procesar',
                'solucion' => 'Verifica que el archivo Excel tenga al menos una fila de datos después de los encabezados'
            ];
            Log::error('❌ Archivo vacío detectado');
            return;
        }

        // ✅ Validar estructura de columnas
        $validacionColumnas = $this->validarColumnasExcel($rows->first());
        if (!$validacionColumnas['valido']) {
            $this->errores[] = [
                'tipo' => 'ESTRUCTURA_INVALIDA',
                'error' => 'El archivo no tiene las columnas requeridas',
                'columnas_faltantes' => $validacionColumnas['faltantes'],
                'columnas_encontradas' => $validacionColumnas['encontradas'],
                'solucion' => 'Asegúrate de que el archivo tenga todas las columnas requeridas en la primera fila'
            ];
            Log::error('❌ Estructura de columnas inválida', [
                'faltantes' => $validacionColumnas['faltantes']
            ]);
            return;
        }

        Log::info('✅ Estructura del Excel validada correctamente');

        // ✅ Agrupar por carnet para procesamiento ordenado
        $pagosPorCarnet = $rows->groupBy('carnet');

        Log::info('📊 Pagos agrupados por carnet', [
            'total_carnets' => $pagosPorCarnet->count(),
            'carnets_muestra' => $pagosPorCarnet->keys()->take(5)->toArray()
        ]);

        // ✅ Procesar cada estudiante
        foreach ($pagosPorCarnet as $carnet => $pagosEstudiante) {
            try {
                $this->procesarPagosDeEstudiante($carnet, $pagosEstudiante);
            } catch (\Throwable $ex) {
                Log::error("❌ Error crítico procesando carnet {$carnet}", [
                    'error' => $ex->getMessage(),
                    'file' => $ex->getFile(),
                    'line' => $ex->getLine(),
                    'trace' => array_slice(explode("\n", $ex->getTraceAsString()), 0, 5)
                ]);

                $this->errores[] = [
                    'tipo' => 'ERROR_PROCESAMIENTO_ESTUDIANTE',
                    'carnet' => $carnet,
                    'error' => $ex->getMessage(),
                    'cantidad_pagos_afectados' => $pagosEstudiante->count()
                ];
            }
        }

        Log::info('=== ✅ PROCESAMIENTO COMPLETADO ===', [
            'total_rows' => $this->totalRows,
            'procesados' => $this->procesados,
            'kardex_creados' => $this->kardexCreados,
            'cuotas_actualizadas' => $this->cuotasActualizadas,
            'conciliaciones' => $this->conciliaciones,
            'total_monto' => round($this->totalAmount, 2),
            'pagos_parciales' => $this->pagosParciales,
            'total_discrepancias' => round($this->totalDiscrepancias, 2),
            'errores' => count($this->errores),
            'advertencias' => count($this->advertencias),
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    private function validarColumnasExcel($primeraFila): array
    {
        $columnasRequeridas = [
            'carnet',
            'nombre_estudiante',
            'numero_boleta',
            'monto',
            'fecha_pago',
            'mensualidad_aprobada'
        ];

        $columnasOpcionales = [
            'banco',
            'concepto',
            'mes_pago',
            'mes_inicio',
            'fila_origen'
        ];

        if (!$primeraFila) {
            return [
                'valido' => false,
                'faltantes' => $columnasRequeridas,
                'encontradas' => []
            ];
        }

        $columnasEncontradas = array_keys($primeraFila->toArray());
        $columnasFaltantes = array_diff($columnasRequeridas, $columnasEncontradas);

        return [
            'valido' => empty($columnasFaltantes),
            'faltantes' => array_values($columnasFaltantes),
            'encontradas' => $columnasEncontradas,
            'opcionales_encontradas' => array_intersect($columnasOpcionales, $columnasEncontradas)
        ];
    }

    private function procesarPagosDeEstudiante($carnet, Collection $pagos)
    {
        $carnetNormalizado = $this->normalizarCarnet($carnet);

        Log::info("=== 👤 PROCESANDO ESTUDIANTE {$carnetNormalizado} ===", [
            'cantidad_pagos' => $pagos->count()
        ]);

        // ✅ Buscar programas del estudiante
        $programasEstudiante = $this->obtenerProgramasEstudiante($carnetNormalizado);

        if ($programasEstudiante->isEmpty()) {
            $this->errores[] = [
                'tipo' => 'ESTUDIANTE_NO_ENCONTRADO',
                'carnet' => $carnetNormalizado,
                'error' => 'No se encontró ningún programa activo para este carnet',
                'cantidad_pagos_afectados' => $pagos->count(),
                'solucion' => 'Verifica que el carnet esté registrado en el sistema y tenga al menos un programa activo'
            ];
            Log::warning("⚠️ Estudiante no encontrado: {$carnetNormalizado}");
            return;
        }

        Log::info("✅ Programas encontrados", [
            'carnet' => $carnetNormalizado,
            'cantidad_programas' => $programasEstudiante->count(),
            'programas' => $programasEstudiante->pluck('nombre_programa', 'estudiante_programa_id')->toArray()
        ]);

        // ✅ Ordenar pagos cronológicamente
        $pagosOrdenados = $pagos->sortBy(function($pago) {
            $fecha = $this->normalizarFecha($pago['fecha_pago']);
            return $fecha ? $fecha->timestamp : 0;
        });

        // ✅ Procesar cada pago
        foreach ($pagosOrdenados as $i => $pago) {
            $numeroFila = $pago['fila_origen'] ?? ($i + 2); // +2 porque Excel empieza en 1 y tiene header

            try {
                $this->procesarPagoIndividual($pago, $programasEstudiante, $numeroFila);
            } catch (\Throwable $ex) {
                $this->errores[] = [
                    'tipo' => 'ERROR_PROCESAMIENTO_PAGO',
                    'fila' => $numeroFila,
                    'carnet' => $carnetNormalizado,
                    'boleta' => $pago['numero_boleta'] ?? 'N/A',
                    'error' => $ex->getMessage(),
                    'trace' => config('app.debug') ? array_slice(explode("\n", $ex->getTraceAsString()), 0, 3) : null
                ];

                Log::error("❌ Error en fila {$numeroFila}", [
                    'carnet' => $carnetNormalizado,
                    'error' => $ex->getMessage()
                ]);
            }
        }
    }

    private function procesarPagoIndividual($row, Collection $programasEstudiante, $numeroFila)
    {
        // ✅ Extraer y normalizar datos
        $carnet = $this->normalizarCarnet($row['carnet']);
        $nombreEstudiante = trim($row['nombre_estudiante'] ?? '');
        $boleta = $this->normalizarBoleta($row['numero_boleta'] ?? '');
        $monto = $this->normalizarMonto($row['monto'] ?? 0);
        $fechaPago = $this->normalizarFecha($row['fecha_pago'] ?? null);
        $bancoRaw = trim((string)($row['banco'] ?? ''));
        $banco = empty($bancoRaw) ? 'EFECTIVO' : $bancoRaw;
        $concepto = trim((string)($row['concepto'] ?? 'Cuota mensual'));
        $mesPago = trim((string)($row['mes_pago'] ?? ''));
        $mesInicio = trim((string)($row['mes_inicio'] ?? ''));
        $mensualidadAprobada = $this->normalizarMonto($row['mensualidad_aprobada'] ?? 0);

        Log::info("📄 Procesando fila {$numeroFila}", [
            'carnet' => $carnet,
            'nombre' => $nombreEstudiante,
            'boleta' => $boleta,
            'monto' => $monto,
            'fecha_pago' => $fechaPago?->toDateString(),
            'mensualidad_aprobada' => $mensualidadAprobada
        ]);

        // ✅ Validaciones básicas mejoradas
        $validacion = $this->validarDatosPago($boleta, $monto, $fechaPago, $numeroFila);
        if (!$validacion['valido']) {
            $this->advertencias[] = $validacion['advertencia'];
            return;
        }

        // ✅ Identificar programa correcto
        $programaAsignado = $this->identificarProgramaCorrecto(
            $programasEstudiante,
            $mensualidadAprobada,
            $monto,
            $fechaPago,
            $mesInicio
        );

        if (!$programaAsignado) {
            $this->errores[] = [
                'tipo' => 'PROGRAMA_NO_IDENTIFICADO',
                'fila' => $numeroFila,
                'carnet' => $carnet,
                'error' => 'No se pudo identificar el programa correcto para este pago',
                'programas_disponibles' => $programasEstudiante->count(),
                'mensualidad' => $mensualidadAprobada,
                'monto_pago' => $monto,
                'fecha_pago' => $fechaPago->toDateString()
            ];
            return;
        }

        // ✅ TRANSACCIÓN con manejo de errores robusto
        try {
            DB::transaction(function () use (
                $programaAsignado, $boleta, $monto, $fechaPago, $banco,
                $concepto, $mesPago, $numeroFila, $carnet, $mensualidadAprobada, $nombreEstudiante
            ) {
                // ✅ Verificar duplicado
                $kardexExistente = KardexPago::where('numero_boleta', $boleta)
                    ->where('estudiante_programa_id', $programaAsignado->estudiante_programa_id)
                    ->first();

                if ($kardexExistente) {
                    Log::info("⚠️ Kardex duplicado detectado", [
                        'kardex_id' => $kardexExistente->id,
                        'boleta' => $boleta,
                        'estudiante_programa_id' => $programaAsignado->estudiante_programa_id
                    ]);

                    $this->advertencias[] = [
                        'tipo' => 'DUPLICADO',
                        'fila' => $numeroFila,
                        'advertencia' => "Pago ya registrado anteriormente",
                        'kardex_id' => $kardexExistente->id,
                        'boleta' => $boleta,
                        'accion' => 'omitido'
                    ];
                    return;
                }

                // 🔥 Buscar cuota con lógica flexible
                $cuota = $this->buscarCuotaFlexible(
                    $programaAsignado->estudiante_programa_id,
                    $fechaPago,
                    $monto,
                    $mensualidadAprobada,
                    $numeroFila
                );

                if (!$cuota) {
                    $this->advertencias[] = [
                        'tipo' => 'SIN_CUOTA',
                        'fila' => $numeroFila,
                        'advertencia' => 'No se encontró cuota pendiente compatible. El Kardex se creará sin cuota asignada.',
                        'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
                        'fecha_pago' => $fechaPago->toDateString(),
                        'monto' => $monto,
                        'recomendacion' => 'Revisar si las cuotas del programa están correctamente configuradas'
                    ];
                }

                // ✅ Crear Kardex con información completa
                $observaciones = sprintf(
                    "%s | Estudiante: %s | Mes: %s | Migración fila %d | Programa: %s",
                    $concepto,
                    $nombreEstudiante,
                    $mesPago,
                    $numeroFila,
                    $programaAsignado->nombre_programa ?? 'N/A'
                );

                $kardex = KardexPago::create([
                    'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
                    'cuota_id' => $cuota ? $cuota->id : null,
                    'numero_boleta' => $boleta,
                    'monto_pagado' => $monto,
                    'fecha_pago' => $fechaPago,
                    'fecha_recibo' => $fechaPago,
                    'banco' => $banco,
                    'estado_pago' => 'aprobado',
                    'observaciones' => $observaciones,
                    'uploaded_by' => $this->uploaderId,
                    'created_by' => $this->uploaderId,
                ]);

                $this->kardexCreados++;
                $this->totalAmount += $monto;

                Log::info("💰 Kardex creado exitosamente", [
                    'kardex_id' => $kardex->id,
                    'cuota_id' => $cuota ? $cuota->id : 'SIN CUOTA',
                    'programa' => $programaAsignado->nombre_programa ?? 'N/A',
                    'monto' => $monto,
                    'fila' => $numeroFila
                ]);

                // ✅ Actualizar cuota y conciliar si existe cuota
                if ($cuota) {
                    $this->actualizarCuotaYConciliar($cuota, $kardex, $numeroFila, $banco, $monto);
                }

                $this->procesados++;

                $this->detalles[] = [
                    'accion' => 'pago_registrado',
                    'fila' => $numeroFila,
                    'carnet' => $carnet,
                    'nombre' => $nombreEstudiante,
                    'kardex_id' => $kardex->id,
                    'cuota_id' => $cuota ? $cuota->id : null,
                    'programa' => $programaAsignado->nombre_programa ?? 'N/A',
                    'monto' => $monto,
                    'fecha_pago' => $fechaPago->toDateString()
                ];
            });
        } catch (\Throwable $ex) {
            Log::error("❌ Error en transacción fila {$numeroFila}", [
                'error' => $ex->getMessage(),
                'carnet' => $carnet,
                'file' => $ex->getFile(),
                'line' => $ex->getLine()
            ]);

            throw $ex; // Re-lanzar para que se capture en el catch superior
        }
    }

    private function buscarCuotaFlexible(
        int $estudianteProgramaId,
        Carbon $fechaPago,
        float $montoPago,
        float $mensualidadAprobada,
        int $numeroFila
    ) {
        $cuotasPendientes = $this->obtenerCuotasDelPrograma($estudianteProgramaId)
            ->where('estado', 'pendiente')
            ->sortBy('fecha_vencimiento');

        if ($cuotasPendientes->isEmpty()) {
            Log::warning("⚠️ No hay cuotas pendientes", [
                'estudiante_programa_id' => $estudianteProgramaId,
                'fila' => $numeroFila
            ]);
            return null;
        }

        Log::info("🔍 Buscando cuota compatible", [
            'cuotas_pendientes' => $cuotasPendientes->count(),
            'monto_pago' => $montoPago,
            'mensualidad_aprobada' => $mensualidadAprobada
        ]);

        // ✅ PRIORIDAD 1: Coincidencia exacta con mensualidad aprobada
        if ($mensualidadAprobada > 0) {
            $cuotaExacta = $cuotasPendientes->first(function($cuota) use ($mensualidadAprobada) {
                $diferencia = abs($cuota->monto - $mensualidadAprobada);
                return $diferencia <= 100; // Tolerancia Q100
            });

            if ($cuotaExacta) {
                Log::info("✅ Cuota encontrada por mensualidad aprobada", [
                    'cuota_id' => $cuotaExacta->id,
                    'monto_cuota' => $cuotaExacta->monto,
                    'monto_pago' => $montoPago,
                    'diferencia' => abs($cuotaExacta->monto - $montoPago)
                ]);
                return $cuotaExacta;
            }
        }

        // ✅ PRIORIDAD 2: Coincidencia con monto de pago (tolerancia amplia)
        $cuotaPorMonto = $cuotasPendientes->first(function($cuota) use ($montoPago) {
            $diferencia = abs($cuota->monto - $montoPago);
            return $diferencia <= 500; // Tolerancia Q500
        });

        if ($cuotaPorMonto) {
            Log::info("✅ Cuota encontrada por monto de pago", [
                'cuota_id' => $cuotaPorMonto->id,
                'monto_cuota' => $cuotaPorMonto->monto,
                'monto_pago' => $montoPago,
                'diferencia' => abs($cuotaPorMonto->monto - $montoPago)
            ]);
            return $cuotaPorMonto;
        }

        // 🔥 PRIORIDAD 3: PAGO PARCIAL (pago menor que la cuota)
        $cuotaParcial = $cuotasPendientes->first(function($cuota) use ($montoPago) {
            if ($cuota->monto == 0) return false; // Evitar división por cero

            $porcentajePago = ($montoPago / $cuota->monto) * 100;
            return $porcentajePago >= 50 && $montoPago < $cuota->monto;
        });

        if ($cuotaParcial) {
            $discrepancia = $cuotaParcial->monto - $montoPago;
            $this->pagosParciales++;
            $this->totalDiscrepancias += $discrepancia;

            Log::warning("⚠️ PAGO PARCIAL DETECTADO", [
                'fila' => $numeroFila,
                'cuota_id' => $cuotaParcial->id,
                'monto_cuota' => $cuotaParcial->monto,
                'monto_pagado' => $montoPago,
                'diferencia' => round($discrepancia, 2),
                'porcentaje_pagado' => round(($montoPago / $cuotaParcial->monto) * 100, 2)
            ]);

            $this->advertencias[] = [
                'tipo' => 'PAGO_PARCIAL',
                'fila' => $numeroFila,
                'advertencia' => sprintf(
                    'Pago parcial: Q%.2f de Q%.2f (falta Q%.2f = %.1f%%)',
                    $montoPago,
                    $cuotaParcial->monto,
                    $discrepancia,
                    ($discrepancia / $cuotaParcial->monto) * 100
                ),
                'cuota_id' => $cuotaParcial->id,
                'monto_cuota' => $cuotaParcial->monto,
                'monto_pagado' => $montoPago,
                'diferencia' => round($discrepancia, 2),
                'recomendacion' => 'Revisar si hubo renegociación, beca o descuento no registrado en el sistema'
            ];

            return $cuotaParcial;
        }

        // ✅ PRIORIDAD 4: Primera cuota pendiente (cronológico - último recurso)
        $primeraCuota = $cuotasPendientes->first();

        if ($primeraCuota) {
            $diferencia = abs($primeraCuota->monto - $montoPago);

            if ($diferencia > 500) {
                Log::warning("⚠️ Gran diferencia entre cuota y pago", [
                    'cuota_id' => $primeraCuota->id,
                    'monto_cuota' => $primeraCuota->monto,
                    'monto_pago' => $montoPago,
                    'diferencia' => round($diferencia, 2)
                ]);

                $this->advertencias[] = [
                    'tipo' => 'DIFERENCIA_MONTO',
                    'fila' => $numeroFila,
                    'advertencia' => sprintf(
                        'Diferencia significativa: Cuota Q%.2f vs Pago Q%.2f (diferencia Q%.2f)',
                        $primeraCuota->monto,
                        $montoPago,
                        $diferencia
                    ),
                    'cuota_id' => $primeraCuota->id,
                    'recomendacion' => 'Verificar si el pago corresponde a esta cuota o si hay error en los montos'
                ];
            }

            Log::info("⚠️ Usando primera cuota pendiente (cronológico)", [
                'cuota_id' => $primeraCuota->id,
                'fecha_vencimiento' => $primeraCuota->fecha_vencimiento,
                'monto_cuota' => $primeraCuota->monto,
                'monto_pago' => $montoPago,
                'diferencia' => round($diferencia, 2)
            ]);

            return $primeraCuota;
        }

        return null;
    }

    private function actualizarCuotaYConciliar($cuota, $kardex, $numeroFila, $banco, $montoPago)
    {
        $diferencia = $cuota->monto - $montoPago;

        // ✅ Marcar como pagada (incluso si es pago parcial)
        $cuota->update([
            'estado' => 'pagado',
            'paid_at' => $kardex->fecha_pago,
        ]);

        $this->cuotasActualizadas++;

        // ✅ Log si hay diferencia significativa
        if (abs($diferencia) > 100) {
            Log::info("💰 Cuota actualizada con diferencia", [
                'cuota_id' => $cuota->id,
                'monto_cuota' => $cuota->monto,
                'monto_pagado' => $montoPago,
                'diferencia' => round($diferencia, 2),
                'tipo' => $diferencia > 0 ? 'PAGO_MENOR' : 'SOBREPAGO'
            ]);
        }

        // ✅ Crear conciliación
        $bancoNormalizado = $this->normalizeBank($banco);
        $boletaNormalizada = $this->normalizeReceiptNumber($kardex->numero_boleta);
        $fechaYmd = Carbon::parse($kardex->fecha_pago)->format('Y-m-d');
        $fingerprint = $this->makeFingerprint($bancoNormalizado, $boletaNormalizada, $kardex->monto_pagado, $fechaYmd);

        // ✅ Verificar duplicado de conciliación
        if (ReconciliationRecord::where('fingerprint', $fingerprint)->exists()) {
            Log::warning("⚠️ Conciliación duplicada detectada", [
                'fingerprint' => $fingerprint,
                'boleta' => $kardex->numero_boleta
            ]);
            return;
        }

        try {
            ReconciliationRecord::create([
                'bank' => $banco,
                'bank_normalized' => $bancoNormalizado,
                'reference' => $kardex->numero_boleta ?: 'HIST-' . $kardex->id,
                'reference_normalized' => $boletaNormalizada ?: 'HIST' . $kardex->id,
                'amount' => $kardex->monto_pagado,
                'date' => $fechaYmd,
                'fingerprint' => $fingerprint,
                'status' => 'conciliado',
                'uploaded_by' => $this->uploaderId,
                'kardex_pago_id' => $kardex->id,
            ]);

            $this->conciliaciones++;

            Log::info("✅ Conciliación creada", [
                'kardex_id' => $kardex->id,
                'fingerprint' => $fingerprint
            ]);
        } catch (\Throwable $e) {
            Log::error("❌ Error creando conciliación", [
                'error' => $e->getMessage(),
                'kardex_id' => $kardex->id,
                'fingerprint' => $fingerprint
            ]);
        }
    }

    private function validarDatosPago($boleta, $monto, $fechaPago, $numeroFila): array
    {
        $errores = [];

        if (empty($boleta) || trim($boleta) === '') {
            $errores[] = 'Boleta vacía o inválida';
        }

        if (!is_numeric($monto) || $monto <= 0) {
            $errores[] = "Monto inválido o negativo: {$monto}";
        }

        if (empty($fechaPago) || !($fechaPago instanceof Carbon)) {
            $errores[] = 'Fecha de pago vacía o inválida';
        }

        if (!empty($errores)) {
            return [
                'valido' => false,
                'advertencia' => [
                    'tipo' => 'DATOS_INCOMPLETOS',
                    'fila' => $numeroFila,
                    'advertencia' => 'Datos incompletos o inválidos',
                    'errores' => $errores,
                    'datos' => [
                        'boleta' => $boleta,
                        'monto' => $monto,
                        'fecha_pago' => $fechaPago instanceof Carbon ? $fechaPago->toDateString() : 'INVÁLIDA'
                    ]
                ]
            ];
        }

        return ['valido' => true];
    }

    private function normalizarBoleta($boleta): string
    {
        $boleta = trim((string)$boleta);

        if (empty($boleta)) {
            return '';
        }

        // ✅ Manejar boletas compuestas como "545109 / 1740192"
        if (str_contains($boleta, '/')) {
            $partes = explode('/', $boleta);
            $original = $boleta;
            $boleta = trim($partes[0]);

            Log::info("📋 Boleta compuesta detectada", [
                'original' => $original,
                'normalizada' => $boleta,
                'segunda_parte' => trim($partes[1] ?? '')
            ]);
        }

        // ✅ Remover caracteres especiales excepto letras y números
        $normalizada = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $boleta));

        return $normalizada;
    }

    private function identificarProgramaCorrecto(
        Collection $programas,
        float $mensualidadAprobada,
        float $montoPago,
        Carbon $fechaPago,
        string $mesInicio
    ) {
        // ✅ Si solo hay un programa, es fácil
        if ($programas->count() === 1) {
            return $programas->first();
        }

        Log::info("🔍 Identificando programa entre {$programas->count()} opciones", [
            'mensualidad_aprobada' => $mensualidadAprobada,
            'monto_pago' => $montoPago,
            'fecha_pago' => $fechaPago->toDateString()
        ]);

        // ✅ ESTRATEGIA 1: Por mensualidad aprobada (tolerancia 33%)
        if ($mensualidadAprobada > 0) {
            foreach ($programas as $programa) {
                $cuotasPrograma = $this->obtenerCuotasDelPrograma($programa->estudiante_programa_id);

                $cuotaCoincidente = $cuotasPrograma->first(function($cuota) use ($mensualidadAprobada) {
                    $diferencia = abs($cuota->monto - $mensualidadAprobada);
                    $tolerancia = max(500, $mensualidadAprobada * 0.33); // 33% o Q500 mínimo
                    return $diferencia <= $tolerancia;
                });

                if ($cuotaCoincidente) {
                    Log::info("✅ Programa identificado por mensualidad aprobada", [
                        'estudiante_programa_id' => $programa->estudiante_programa_id,
                        'programa' => $programa->nombre_programa,
                        'mensualidad' => $mensualidadAprobada,
                        'cuota_monto' => $cuotaCoincidente->monto
                    ]);
                    return $programa;
                }
            }
        }

        // ✅ ESTRATEGIA 2: Por rango de fechas del programa
        foreach ($programas as $programa) {
            $cuotasPrograma = $this->obtenerCuotasDelPrograma($programa->estudiante_programa_id);

            if ($cuotasPrograma->isEmpty()) {
                continue;
            }

            $primeraFecha = $cuotasPrograma->min('fecha_vencimiento');
            $ultimaFecha = $cuotasPrograma->max('fecha_vencimiento');

            // ✅ Agregar margen de 30 días antes y después
            if ($fechaPago->between(
                Carbon::parse($primeraFecha)->subDays(30),
                Carbon::parse($ultimaFecha)->addDays(30)
            )) {
                Log::info("✅ Programa identificado por rango de fechas", [
                    'estudiante_programa_id' => $programa->estudiante_programa_id,
                    'programa' => $programa->nombre_programa,
                    'rango' => "{$primeraFecha} - {$ultimaFecha}",
                    'fecha_pago' => $fechaPago->toDateString()
                ]);
                return $programa;
            }
        }

        // ✅ ESTRATEGIA 3: Por monto del pago (tolerancia amplia)
        foreach ($programas as $programa) {
            $cuotasPrograma = $this->obtenerCuotasDelPrograma($programa->estudiante_programa_id);

            $cuotaCoincidente = $cuotasPrograma->first(function($cuota) use ($montoPago) {
                $diferencia = abs($cuota->monto - $montoPago);
                $tolerancia = max(500, $cuota->monto * 0.33); // 33%
                return $diferencia <= $tolerancia;
            });

            if ($cuotaCoincidente) {
                Log::info("⚠️ Programa identificado por monto de pago (fallback)", [
                    'estudiante_programa_id' => $programa->estudiante_programa_id,
                    'programa' => $programa->nombre_programa,
                    'monto_pago' => $montoPago
                ]);
                return $programa;
            }
        }

        // ✅ FALLBACK: Usar el más reciente
        Log::warning("⚠️ No se pudo identificar programa específico, usando el más reciente", [
            'programas_disponibles' => $programas->count()
        ]);
        return $programas->first();
    }

    private function obtenerProgramasEstudiante($carnet)
    {
        // ✅ Usar cache para evitar consultas repetidas
        if (isset($this->estudiantesCache[$carnet])) {
            return $this->estudiantesCache[$carnet];
        }

        $programas = DB::table('prospectos as p')
            ->select(
                'p.id as prospecto_id',
                'p.carnet',
                'p.nombres',
                'p.apellidos',
                'ep.id as estudiante_programa_id',
                'ep.programa_id',
                'ep.created_at as fecha_inscripcion',
                'prog.nombre_programa'
            )
            ->join('estudiante_programa as ep', 'p.id', '=', 'ep.prospecto_id')
            ->leftJoin('programas as prog', 'ep.programa_id', '=', 'prog.id')
            ->whereRaw("REPLACE(UPPER(p.carnet), ' ', '') = ?", [$carnet])
            ->where('ep.estado', '=', 'activo')
            ->orderBy('ep.created_at', 'desc') // Más recientes primero
            ->get();

        $this->estudiantesCache[$carnet] = $programas;

        return $programas;
    }

    private function obtenerCuotasDelPrograma(int $estudianteProgramaId)
    {
        // ✅ Usar cache para evitar consultas repetidas
        if (isset($this->cuotasPorEstudianteCache[$estudianteProgramaId])) {
            return $this->cuotasPorEstudianteCache[$estudianteProgramaId];
        }

        $cuotas = CuotaProgramaEstudiante::where('estudiante_programa_id', $estudianteProgramaId)
            ->orderBy('fecha_vencimiento', 'asc')
            ->get();

        $this->cuotasPorEstudianteCache[$estudianteProgramaId] = $cuotas;

        return $cuotas;
    }

    private function normalizeBank($bank)
    {
        if (empty($bank) || $bank === 'N/A' || $bank === 'No especificado') {
            return 'EFECTIVO';
        }

        $bank = strtoupper(trim($bank));

        $bankMappings = [
            'BAC' => 'BAC',
            'BI' => 'BI',
            'BANCO INDUSTRIAL' => 'BI',
            'BANTRAB' => 'BANTRAB',
            'PROMERICA' => 'PROMERICA',
            'GYT' => 'GYT',
            'VISA' => 'VISA',
            'MASTERCARD' => 'MASTERCARD',
        ];

        foreach ($bankMappings as $key => $normalized) {
            if (str_contains($bank, $key)) {
                return $normalized;
            }
        }

        return $bank;
    }

    private function normalizeReceiptNumber($receiptNumber)
    {
        if (empty($receiptNumber)) {
            return 'N/A';
        }

        return strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $receiptNumber));
    }

    private function makeFingerprint($bank, $reference, $amount, $date)
    {
        $data = [
            'bank' => $bank,
            'reference' => $reference,
            'amount' => round($amount, 2),
            'date' => $date
        ];

        return md5(implode('|', $data));
    }

    private function normalizarCarnet($carnet)
    {
        $normalizado = strtoupper(preg_replace('/\s+/', '', trim($carnet)));

        Log::debug('🎫 Carnet normalizado', [
            'original' => $carnet,
            'normalizado' => $normalizado
        ]);

        return $normalizado;
    }

    private function normalizarMonto($monto)
    {
        if (is_string($monto)) {
            // ✅ Remover símbolos de moneda, comas, espacios
            $monto = preg_replace('/[Q$,\s]/', '', $monto);
        }

        $resultado = floatval($monto);

        Log::debug('💵 Monto normalizado', [
            'original' => $monto,
            'resultado' => $resultado
        ]);

        return $resultado;
    }

    private function normalizarFecha($fecha)
    {
        try {
            // ✅ Manejar fechas numéricas de Excel
            if (is_numeric($fecha)) {
                // Excel usa 1899-12-30 como fecha base correcta
                $baseDate = Carbon::create(1899, 12, 30);
                $resultado = $baseDate->addDays(intval($fecha));

                Log::debug('📅 Fecha normalizada desde Excel', [
                    'original' => $fecha,
                    'resultado' => $resultado->toDateString()
                ]);

                return $resultado;
            }

            // ✅ Manejar strings vacíos
            if (empty($fecha) || trim($fecha) === '') {
                Log::debug('⚠️ Fecha vacía detectada');
                return null;
            }

            // ✅ Parsear fecha como string
            $resultado = Carbon::parse($fecha);

            Log::debug('📅 Fecha parseada desde string', [
                'original' => $fecha,
                'resultado' => $resultado->toDateString()
            ]);

            return $resultado;

        } catch (\Exception $e) {
            Log::warning('⚠️ Error normalizando fecha', [
                'fecha' => $fecha,
                'tipo' => gettype($fecha),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
