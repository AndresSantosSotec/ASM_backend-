<?php

namespace App\Imports;

// ✅ AGREGAR ESTAS LÍNEAS AL INICIO
ini_set('memory_limit', '2048M'); // 1 GB
ini_set('max_execution_time', '1500'); // 10 minutos

use App\Services\EstudianteService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\KardexPago;
use App\Models\CuotaProgramaEstudiante;
use App\Models\ReconciliationRecord;
use App\Models\PrecioPrograma;
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

    // 🆕 NUEVO: Servicio de estudiantes
    private EstudianteService $estudianteService;

    // 🆕 NUEVO: Modo reemplazo de cuotas pendientes
    private bool $modoReemplazoPendientes = false;

    public function __construct(int $uploaderId, string $tipoArchivo = 'cardex_directo', bool $modoReemplazoPendientes = false)
    {
        $this->uploaderId = $uploaderId;
        $this->tipoArchivo = $tipoArchivo;
        $this->modoReemplazoPendientes = $modoReemplazoPendientes;
        $this->estudianteService = new EstudianteService();

        Log::info('📦 PaymentHistoryImport Constructor', [
            'uploaderId' => $uploaderId,
            'tipoArchivo' => $tipoArchivo,
            'modoReemplazoPendientes' => $modoReemplazoPendientes,
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

        // 🆕 NUEVO: Resumen de registros exitosos
        if (!empty($this->detalles)) {
            Log::info('📊 RESUMEN DE REGISTROS IMPORTADOS EXITOSAMENTE', [
                'total_exitosos' => count($this->detalles),
                'monto_total_procesado' => 'Q' . number_format($this->totalAmount, 2)
            ]);

            // Agrupar por programa
            $porPrograma = collect($this->detalles)->groupBy('programa');

            foreach ($porPrograma as $programa => $registros) {
                $montoPrograma = collect($registros)->sum('monto');

                Log::info("✅ Programa: {$programa}", [
                    'cantidad_pagos' => $registros->count(),
                    'monto_total' => 'Q' . number_format($montoPrograma, 2),
                    'pagos' => collect($registros)->map(function ($detalle) {
                        return [
                            'fila' => $detalle['fila'],
                            'carnet' => $detalle['carnet'],
                            'nombre' => $detalle['nombre'],
                            'kardex_id' => $detalle['kardex_id'],
                            'cuota_id' => $detalle['cuota_id'] ?? 'SIN CUOTA',
                            'monto' => 'Q' . number_format($detalle['monto'], 2),
                            'fecha' => $detalle['fecha_pago']
                        ];
                    })->toArray()
                ]);
            }

            // 🆕 Resumen por estudiante
            $porEstudiante = collect($this->detalles)->groupBy('carnet');

            Log::info('📋 RESUMEN POR ESTUDIANTE', [
                'total_estudiantes_procesados' => $porEstudiante->count()
            ]);

            foreach ($porEstudiante as $carnet => $registros) {
                $montoEstudiante = collect($registros)->sum('monto');

                Log::info("👤 Estudiante: {$carnet}", [
                    'nombre' => $registros->first()['nombre'] ?? 'N/A',
                    'cantidad_pagos' => $registros->count(),
                    'monto_total' => 'Q' . number_format($montoEstudiante, 2),
                    'programa' => $registros->first()['programa'] ?? 'N/A',
                    'kardex_ids_creados' => collect($registros)->pluck('kardex_id')->toArray()
                ]);
            }
        }

        // 📊 Resumen detallado de errores si los hay
        if (!empty($this->errores)) {
            $erroresPorTipo = collect($this->errores)->groupBy('tipo');
            Log::warning('📊 RESUMEN DE ERRORES POR TIPO', [
                'total_errores' => count($this->errores),
                'tipos' => $erroresPorTipo->map(function ($errores, $tipo) {
                    return [
                        'cantidad' => $errores->count(),
                        'ejemplos' => $errores->take(3)->map(function ($error) {
                            $details = ['mensaje' => $error['error'] ?? 'Error desconocido'];
                            if (isset($error['carnet'])) $details['carnet'] = $error['carnet'];
                            if (isset($error['fila'])) $details['fila'] = $error['fila'];
                            if (isset($error['boleta'])) $details['boleta'] = $error['boleta'];
                            if (isset($error['cantidad_pagos_afectados'])) $details['pagos_afectados'] = $error['cantidad_pagos_afectados'];
                            if (isset($error['solucion'])) $details['solucion'] = $error['solucion'];
                            return $details;
                        })->toArray()
                    ];
                })->toArray()
            ]);

            // Log detailed breakdown for each error type
            foreach ($erroresPorTipo as $tipo => $errores) {
                Log::warning("🔍 Detalle de {$tipo}", [
                    'total' => $errores->count(),
                    'descripcion' => $this->getErrorTypeDescription($tipo),
                    'primeros_5_casos' => $errores->take(5)->map(function ($error) {
                        return [
                            'carnet' => $error['carnet'] ?? 'N/A',
                            'fila' => $error['fila'] ?? 'N/A',
                            'mensaje' => $error['error'] ?? 'Sin descripción',
                            'pagos_afectados' => $error['cantidad_pagos_afectados'] ?? 1
                        ];
                    })->toArray()
                ]);
            }
        }

        // 📊 Resumen de advertencias si las hay
        if (!empty($this->advertencias)) {
            $advertenciasPorTipo = collect($this->advertencias)->groupBy('tipo');
            Log::info('📊 RESUMEN DE ADVERTENCIAS POR TIPO', [
                'total_advertencias' => count($this->advertencias),
                'tipos' => $advertenciasPorTipo->map(function ($advertencias, $tipo) {
                    return [
                        'cantidad' => $advertencias->count()
                    ];
                })->toArray()
            ]);
        }

        // 🆕 NUEVO: Resumen final consolidado
        Log::info('=' . str_repeat('=', 80));
        Log::info('🎯 RESUMEN FINAL DE IMPORTACIÓN');
        Log::info('=' . str_repeat('=', 80));
        Log::info('✅ EXITOSOS', [
            'filas_procesadas' => $this->procesados,
            'kardex_creados' => $this->kardexCreados,
            'cuotas_actualizadas' => $this->cuotasActualizadas,
            'conciliaciones_creadas' => $this->conciliaciones,
            'monto_total' => 'Q' . number_format($this->totalAmount, 2),
            'porcentaje_exito' => $this->totalRows > 0
                ? round(($this->procesados / $this->totalRows) * 100, 2) . '%'
                : '0%'
        ]);

        Log::info('⚠️ ADVERTENCIAS', [
            'total' => count($this->advertencias),
            'sin_cuota' => collect($this->advertencias)->where('tipo', 'SIN_CUOTA')->count(),
            'duplicados' => collect($this->advertencias)->where('tipo', 'DUPLICADO')->count(),
            'pagos_parciales' => $this->pagosParciales,
            'diferencias_monto' => collect($this->advertencias)->where('tipo', 'DIFERENCIA_MONTO')->count()
        ]);

        $erroresCollection = collect($this->errores);
        Log::info('❌ ERRORES', [
            'total' => count($this->errores),
            'estudiantes_no_encontrados' => $erroresCollection->where('tipo', 'ESTUDIANTE_NO_ENCONTRADO')->count(),
            'programas_no_identificados' => $erroresCollection->where('tipo', 'PROGRAMA_NO_IDENTIFICADO')->count(),
            'datos_incompletos' => $erroresCollection->where('tipo', 'DATOS_INCOMPLETOS')->count(),
            'errores_procesamiento_pago' => $erroresCollection->where('tipo', 'ERROR_PROCESAMIENTO_PAGO')->count(),
            'errores_procesamiento_estudiante' => $erroresCollection->where('tipo', 'ERROR_PROCESAMIENTO_ESTUDIANTE')->count(),
            'archivo_vacio' => $erroresCollection->where('tipo', 'ARCHIVO_VACIO')->count(),
            'estructura_invalida' => $erroresCollection->where('tipo', 'ESTRUCTURA_INVALIDA')->count()
        ]);

        Log::info('=' . str_repeat('=', 80));
    }

    /**
     * 🆕 Método para obtener reporte detallado de éxitos
     */
    public function getReporteExitos(): array
    {
        if (empty($this->detalles)) {
            return [
                'mensaje' => 'No hay registros exitosos para reportar',
                'total' => 0
            ];
        }

        $registrosExitosos = collect($this->detalles);

        return [
            'resumen' => [
                'total_registros' => $registrosExitosos->count(),
                'monto_total' => round($this->totalAmount, 2),
                'kardex_creados' => $this->kardexCreados,
                'cuotas_actualizadas' => $this->cuotasActualizadas,
                'conciliaciones' => $this->conciliaciones,
            ],
            'por_programa' => $registrosExitosos->groupBy('programa')->map(function ($registros, $programa) {
                return [
                    'programa' => $programa,
                    'cantidad' => $registros->count(),
                    'monto_total' => round($registros->sum('monto'), 2),
                    'registros' => $registros->values()->toArray()
                ];
            })->values()->toArray(),
            'por_estudiante' => $registrosExitosos->groupBy('carnet')->map(function ($registros, $carnet) {
                return [
                    'carnet' => $carnet,
                    'nombre' => $registros->first()['nombre'] ?? 'N/A',
                    'programa' => $registros->first()['programa'] ?? 'N/A',
                    'cantidad_pagos' => $registros->count(),
                    'monto_total' => round($registros->sum('monto'), 2),
                    'kardex_ids' => $registros->pluck('kardex_id')->toArray(),
                    'fechas_procesadas' => $registros->pluck('fecha_pago')->unique()->values()->toArray()
                ];
            })->values()->toArray(),
            'detalle_completo' => $registrosExitosos->sortBy('fila')->values()->toArray()
        ];
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
            'plan_estudios',
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

    /**
     * 🔥 MÉTODO MEJORADO: Ahora crea estudiantes/programas si no existen
     */
    private function procesarPagosDeEstudiante($carnet, Collection $pagos)
    {
        $carnetNormalizado = $this->normalizarCarnet($carnet);

        Log::info("=== 👤 PROCESANDO ESTUDIANTE {$carnetNormalizado} ===", [
            'cantidad_pagos' => $pagos->count()
        ]);

        // 🔥 CAMBIO: Pasar primer pago como contexto para creación
        $primerPago = $pagos->first();
        $programasEstudiante = $this->obtenerProgramasEstudiante($carnetNormalizado, $primerPago);

        if ($programasEstudiante->isEmpty()) {
            $this->errores[] = [
                'tipo' => 'ESTUDIANTE_NO_ENCONTRADO',
                'carnet' => $carnetNormalizado,
                'error' => 'No se pudo crear ni encontrar programas para este carnet',
                'cantidad_pagos_afectados' => $pagos->count(),
                'solucion' => 'Verifica los datos del Excel y que el carnet sea válido'
            ];
            Log::warning("⚠️ Estudiante no encontrado/creado: {$carnetNormalizado}");
            return;
        }

        Log::info("✅ Programas encontrados/creados", [
            'carnet' => $carnetNormalizado,
            'cantidad_programas' => $programasEstudiante->count(),
            'programas' => $programasEstudiante->pluck('nombre_programa', 'estudiante_programa_id')->toArray()
        ]);

        // ✅ Ordenar pagos cronológicamente
        $pagosOrdenados = $pagos->sortBy(function ($pago) {
            $fecha = $this->normalizarFecha($pago['fecha_pago']);
            return $fecha ? $fecha->timestamp : 0;
        });

        // ✅ Procesar cada pago
        foreach ($pagosOrdenados as $i => $pago) {
            $numeroFila = $pago['fila_origen'] ?? ($i + 2);

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
                $programaAsignado,
                $boleta,
                $monto,
                $fechaPago,
                $banco,
                $concepto,
                $mesPago,
                $numeroFila,
                $carnet,
                $mensualidadAprobada,
                $nombreEstudiante
            ) {
                // ✅ Verificar duplicado por boleta y estudiante
                $kardexExistente = KardexPago::where('numero_boleta', $boleta)
                    ->where('estudiante_programa_id', $programaAsignado->estudiante_programa_id)
                    ->first();

                if ($kardexExistente) {
                    Log::info("⚠️ Kardex duplicado detectado (por boleta+estudiante)", [
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

                // ✅ Verificar duplicado por fingerprint (más preciso, incluye fecha)
                $bancoNormalizado = $this->normalizeBank($banco);
                $boletaNormalizada = $this->normalizeReceiptNumber($boleta);
                $fechaYmd = $fechaPago->format('Y-m-d');
                $fingerprint = hash('sha256',
                    $bancoNormalizado.'|'.$boletaNormalizada.'|'.$programaAsignado->estudiante_programa_id.'|'.$fechaYmd);

                $kardexPorFingerprint = KardexPago::where('boleta_fingerprint', $fingerprint)->first();

                if ($kardexPorFingerprint) {
                    Log::info("⚠️ Kardex duplicado detectado (por fingerprint)", [
                        'kardex_id' => $kardexPorFingerprint->id,
                        'fingerprint' => $fingerprint,
                        'boleta' => $boleta,
                        'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
                        'fecha_pago' => $fechaYmd
                    ]);

                    $this->advertencias[] = [
                        'tipo' => 'DUPLICADO',
                        'fila' => $numeroFila,
                        'advertencia' => "Pago ya registrado anteriormente (mismo fingerprint)",
                        'kardex_id' => $kardexPorFingerprint->id,
                        'boleta' => $boleta,
                        'fingerprint' => substr($fingerprint, 0, 16) . '...',
                        'accion' => 'omitido'
                    ];
                    return;
                }

                // 🔥 Buscar cuota con lógica flexible
                Log::info("🔍 Buscando cuota para asignar al pago", [
                    'fila' => $numeroFila,
                    'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
                    'fecha_pago' => $fechaPago->toDateString(),
                    'monto' => $monto,
                    'mensualidad_aprobada' => $mensualidadAprobada
                ]);

                $cuota = $this->buscarCuotaFlexible(
                    $programaAsignado->estudiante_programa_id,
                    $fechaPago,
                    $monto,
                    $mensualidadAprobada,
                    $numeroFila
                );

                if (!$cuota) {
                    Log::warning("⚠️ No se encontró cuota pendiente para este pago", [
                        'fila' => $numeroFila,
                        'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
                        'fecha_pago' => $fechaPago->toDateString(),
                        'monto' => $monto
                    ]);

                    $this->advertencias[] = [
                        'tipo' => 'SIN_CUOTA',
                        'fila' => $numeroFila,
                        'advertencia' => 'No se encontró cuota pendiente compatible. El Kardex se creará sin cuota asignada.',
                        'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
                        'fecha_pago' => $fechaPago->toDateString(),
                        'monto' => $monto,
                        'recomendacion' => 'Revisar si las cuotas del programa están correctamente configuradas'
                    ];
                } else {
                    Log::info("✅ Cuota asignada al pago", [
                        'fila' => $numeroFila,
                        'cuota_id' => $cuota->id,
                        'numero_cuota' => $cuota->numero_cuota,
                        'monto_cuota' => $cuota->monto
                    ]);
                }

                // ✅ Crear Kardex con información completa
                Log::info("🔍 Creando registro en kardex_pagos", [
                    'fila' => $numeroFila,
                    'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
                    'cuota_id' => $cuota ? $cuota->id : null,
                    'numero_boleta' => $boleta,
                    'monto' => $monto,
                    'banco' => $banco
                ]);

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
                ]);

                $this->kardexCreados++;
                $this->totalAmount += $monto;

                Log::info("✅ Kardex creado exitosamente", [
                    'kardex_id' => $kardex->id,
                    'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
                    'cuota_id' => $cuota ? $cuota->id : 'SIN CUOTA',
                    'programa' => $programaAsignado->nombre_programa ?? 'N/A',
                    'numero_boleta' => $boleta,
                    'monto' => $monto,
                    'fila' => $numeroFila
                ]);

                // ✅ Actualizar cuota y conciliar si existe cuota
                if ($cuota) {
                    $this->actualizarCuotaYConciliar($cuota, $kardex, $numeroFila, $banco, $monto);
                } else {
                    Log::info("⏭️ Saltando actualización de cuota (no se asignó cuota)", [
                        'kardex_id' => $kardex->id,
                        'fila' => $numeroFila
                    ]);
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

            // ✅ Add error to array and continue processing (don't re-throw)
            $this->errores[] = [
                'tipo' => 'ERROR_PROCESAMIENTO_PAGO',
                'fila' => $numeroFila,
                'carnet' => $carnet,
                'boleta' => $boleta ?? 'N/A',
                'error' => $ex->getMessage(),
                'trace' => config('app.debug') ? array_slice(explode("\n", $ex->getTraceAsString()), 0, 3) : null
            ];

            // Don't re-throw - allow processing to continue with next payment
        }
    }

    private function buscarCuotaFlexible(
        int $estudianteProgramaId,
        Carbon $fechaPago,
        float $montoPago,
        float $mensualidadAprobada,
        int $numeroFila
    ) {
        // 🔄 NUEVO: Si modo reemplazo está activo, buscar y reemplazar cuota pendiente
        if ($this->modoReemplazoPendientes) {
            $cuotaReemplazada = $this->reemplazarCuotaPendiente(
                $estudianteProgramaId,
                $fechaPago,
                $montoPago,
                $mensualidadAprobada,
                $numeroFila
            );
            
            if ($cuotaReemplazada) {
                return $cuotaReemplazada;
            }
        }

        $cuotasPendientes = $this->obtenerCuotasDelPrograma($estudianteProgramaId)
            ->where('estado', 'pendiente')
            ->sortBy('fecha_vencimiento');

        // 🔥 NUEVO: Si no hay cuotas, intentar generarlas automáticamente
        if ($cuotasPendientes->isEmpty()) {
            Log::warning("⚠️ No hay cuotas pendientes para este programa", [
                'estudiante_programa_id' => $estudianteProgramaId,
                'fila' => $numeroFila
            ]);

            // Intentar generar cuotas automáticamente
            $generado = $this->generarCuotasSiFaltan($estudianteProgramaId, null);

            if ($generado) {
                // Recargar cuotas después de la generación
                $cuotasPendientes = $this->obtenerCuotasDelPrograma($estudianteProgramaId)
                    ->where('estado', 'pendiente')
                    ->sortBy('fecha_vencimiento');

                Log::info("✅ Cuotas generadas y recargadas", [
                    'estudiante_programa_id' => $estudianteProgramaId,
                    'cuotas_disponibles' => $cuotasPendientes->count()
                ]);

                // Si aún no hay cuotas después de generar, retornar null
                if ($cuotasPendientes->isEmpty()) {
                    return null;
                }
            } else {
                // Si no se pudieron generar, intentar al menos validar con el precio del programa
                $precioPrograma = $this->obtenerPrecioPrograma($estudianteProgramaId);
                if ($precioPrograma) {
                    Log::info("💰 Precio de programa encontrado para validación", [
                        'estudiante_programa_id' => $estudianteProgramaId,
                        'cuota_mensual' => $precioPrograma->cuota_mensual,
                        'inscripcion' => $precioPrograma->inscripcion,
                        'monto_pago' => $montoPago
                    ]);

                    // Validar si el monto coincide con el precio del programa
                    $tolerancia = max(100, $precioPrograma->cuota_mensual * 0.50);
                    $diferenciaCuota = abs($precioPrograma->cuota_mensual - $montoPago);
                    $diferenciaInscripcion = abs($precioPrograma->inscripcion - $montoPago);

                    if ($diferenciaCuota <= $tolerancia || $diferenciaInscripcion <= $tolerancia) {
                        Log::info("✅ Monto validado contra precio de programa", [
                            'monto_pago' => $montoPago,
                            'cuota_mensual_programa' => $precioPrograma->cuota_mensual,
                            'inscripcion_programa' => $precioPrograma->inscripcion,
                            'tolerancia' => $tolerancia
                        ]);
                    }
                }

                return null;
            }
        }

        Log::info("🔍 Buscando cuota compatible", [
            'cuotas_pendientes' => $cuotasPendientes->count(),
            'monto_pago' => $montoPago,
            'mensualidad_aprobada' => $mensualidadAprobada,
            'fila' => $numeroFila
        ]);

        // ✅ PRIORIDAD 1: Coincidencia exacta con mensualidad aprobada
        // 🔥 TOLERANCIA MÁXIMA: 50% o mínimo Q100 para importación histórica
        if ($mensualidadAprobada > 0) {
            $tolerancia = max(100, $mensualidadAprobada * 0.50);
            $cuotaExacta = $cuotasPendientes->first(function ($cuota) use ($mensualidadAprobada, $tolerancia) {
                $diferencia = abs($cuota->monto - $mensualidadAprobada);
                return $diferencia <= $tolerancia;
            });

            if ($cuotaExacta) {
                Log::info("✅ Cuota encontrada por mensualidad aprobada", [
                    'cuota_id' => $cuotaExacta->id,
                    'monto_cuota' => $cuotaExacta->monto,
                    'mensualidad_aprobada' => $mensualidadAprobada,
                    'monto_pago' => $montoPago,
                    'diferencia' => abs($cuotaExacta->monto - $mensualidadAprobada),
                    'tolerancia_usada' => $tolerancia
                ]);
                return $cuotaExacta;
            }
        }

        // ✅ PRIORIDAD 2: Coincidencia con monto de pago
        // 🔥 TOLERANCIA MÁXIMA: 50% o mínimo Q100 para importación histórica
        $tolerancia = max(100, $montoPago * 0.50);
        $cuotaPorMonto = $cuotasPendientes->first(function ($cuota) use ($montoPago, $tolerancia) {
            $diferencia = abs($cuota->monto - $montoPago);
            return $diferencia <= $tolerancia;
        });

        if ($cuotaPorMonto) {
            Log::info("✅ Cuota encontrada por monto de pago", [
                'cuota_id' => $cuotaPorMonto->id,
                'monto_cuota' => $cuotaPorMonto->monto,
                'monto_pago' => $montoPago,
                'diferencia' => abs($cuotaPorMonto->monto - $montoPago),
                'tolerancia_usada' => $tolerancia
            ]);
            return $cuotaPorMonto;
        }

        // 🔥 PRIORIDAD 3: PAGO PARCIAL (con tolerancia aumentada)
        // Ahora acepta desde 30% del monto de la cuota (antes era 50%)
        $cuotaParcial = $cuotasPendientes->first(function ($cuota) use ($montoPago) {
            if ($cuota->monto == 0) return false;

            $porcentajePago = ($montoPago / $cuota->monto) * 100;
            return $porcentajePago >= 30 && $montoPago < $cuota->monto;
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

        // 🔥 PRIORIDAD 4: CUALQUIER CUOTA PENDIENTE (tolerancia extrema para importación histórica)
        // Buscar cualquier cuota que esté cerca del monto, con tolerancia del 100%
        $cuotaToleranciaExtrema = $cuotasPendientes->first(function ($cuota) use ($montoPago) {
            $diferencia = abs($cuota->monto - $montoPago);
            $toleranciaExtrema = max($cuota->monto, $montoPago);
            return $diferencia <= $toleranciaExtrema;
        });

        if ($cuotaToleranciaExtrema) {
            $diferencia = abs($cuotaToleranciaExtrema->monto - $montoPago);

            Log::warning("⚠️ Cuota encontrada con tolerancia extrema (100%)", [
                'cuota_id' => $cuotaToleranciaExtrema->id,
                'monto_cuota' => $cuotaToleranciaExtrema->monto,
                'monto_pago' => $montoPago,
                'diferencia' => round($diferencia, 2),
                'porcentaje_diferencia' => $cuotaToleranciaExtrema->monto > 0
                    ? round(($diferencia / $cuotaToleranciaExtrema->monto) * 100, 2)
                    : 0
            ]);

            $this->advertencias[] = [
                'tipo' => 'DIFERENCIA_MONTO_EXTREMA',
                'fila' => $numeroFila,
                'advertencia' => sprintf(
                    'Gran diferencia: Cuota Q%.2f vs Pago Q%.2f (diferencia Q%.2f)',
                    $cuotaToleranciaExtrema->monto,
                    $montoPago,
                    $diferencia
                ),
                'cuota_id' => $cuotaToleranciaExtrema->id,
                'recomendacion' => 'Revisar si el pago corresponde a esta cuota o si hay ajuste de precio'
            ];

            return $cuotaToleranciaExtrema;
        }

        // ✅ PRIORIDAD 5: Primera cuota pendiente (sin restricción de monto)
        // Si llegamos aquí, tomar cualquier cuota pendiente para no perder el pago
        $primeraCuota = $cuotasPendientes->first();

        if ($primeraCuota) {
            $diferencia = abs($primeraCuota->monto - $montoPago);

            Log::warning("⚠️ Usando primera cuota pendiente sin validación de monto (última opción)", [
                'cuota_id' => $primeraCuota->id,
                'fecha_vencimiento' => $primeraCuota->fecha_vencimiento,
                'monto_cuota' => $primeraCuota->monto,
                'monto_pago' => $montoPago,
                'diferencia' => round($diferencia, 2)
            ]);

            $this->advertencias[] = [
                'tipo' => 'CUOTA_FORZADA',
                'fila' => $numeroFila,
                'advertencia' => sprintf(
                    'Cuota asignada forzadamente: Cuota Q%.2f vs Pago Q%.2f (diferencia Q%.2f)',
                    $primeraCuota->monto,
                    $montoPago,
                    $diferencia
                ),
                'cuota_id' => $primeraCuota->id,
                'recomendacion' => 'Verificar manualmente esta asignación de cuota'
            ];

            return $primeraCuota;
        }

        return null;
    }

    /**
     * 🔄 Buscar y reemplazar cuota pendiente con estado "Pagado"
     * Este método se usa cuando modoReemplazoPendientes está activo
     */
    private function reemplazarCuotaPendiente(
        int $estudianteProgramaId,
        Carbon $fechaPago,
        float $montoPago,
        float $mensualidadAprobada,
        int $numeroFila
    ) {
        // Buscar cuotas pendientes ordenadas por fecha de vencimiento
        $cuotasPendientes = $this->obtenerCuotasDelPrograma($estudianteProgramaId)
            ->where('estado', 'pendiente')
            ->sortBy('fecha_vencimiento');

        if ($cuotasPendientes->isEmpty()) {
            return null;
        }

        Log::info("🔄 Modo reemplazo activo: buscando cuota pendiente para reemplazar", [
            'estudiante_programa_id' => $estudianteProgramaId,
            'cuotas_pendientes' => $cuotasPendientes->count(),
            'monto_pago' => $montoPago,
            'mensualidad_aprobada' => $mensualidadAprobada,
            'fila' => $numeroFila
        ]);

        // 🔍 PRIORIDAD 1: Buscar por mensualidad aprobada (si está disponible)
        $cuotaCompatible = null;
        if ($mensualidadAprobada > 0) {
            $tolerancia = max(100, $mensualidadAprobada * 0.50);
            $cuotaCompatible = $cuotasPendientes->first(function ($cuota) use ($mensualidadAprobada, $tolerancia) {
                $diferencia = abs($cuota->monto - $mensualidadAprobada);
                return $diferencia <= $tolerancia;
            });
        }

        // 🔍 PRIORIDAD 2: Buscar por monto de pago
        if (!$cuotaCompatible) {
            $tolerancia = max(100, $montoPago * 0.50);
            $cuotaCompatible = $cuotasPendientes->first(function ($cuota) use ($montoPago, $tolerancia) {
                $diferencia = abs($cuota->monto - $montoPago);
                return $diferencia <= $tolerancia;
            });
        }

        // 🔍 PRIORIDAD 3: Primera cuota pendiente
        if (!$cuotaCompatible) {
            $cuotaCompatible = $cuotasPendientes->first();
        }

        if ($cuotaCompatible) {
            Log::info("🔄 Reemplazando cuota pendiente con pago", [
                'cuota_id' => $cuotaCompatible->id,
                'numero_cuota' => $cuotaCompatible->numero_cuota,
                'monto_cuota_original' => $cuotaCompatible->monto,
                'monto_pago' => $montoPago,
                'estado_anterior' => 'pendiente',
                'estado_nuevo' => 'pagado',
                'fila' => $numeroFila
            ]);

            // Actualizar la cuota a estado pagado
            $cuotaCompatible->update([
                'estado' => 'pagado',
                'paid_at' => $fechaPago,
            ]);

            // Limpiar cache para forzar recarga
            unset($this->cuotasPorEstudianteCache[$estudianteProgramaId]);

            return $cuotaCompatible;
        }

        return null;
    }

    private function actualizarCuotaYConciliar($cuota, $kardex, $numeroFila, $banco, $montoPago)
    {
        $diferencia = $cuota->monto - $montoPago;

        Log::info("🔄 PASO 5: Actualizando estado de cuota", [
            'fila' => $numeroFila,
            'cuota_id' => $cuota->id,
            'numero_cuota' => $cuota->numero_cuota,
            'monto_cuota' => $cuota->monto,
            'monto_pago' => $montoPago,
            'diferencia' => round($diferencia, 2),
            'estado_anterior' => $cuota->estado
        ]);

        $cuota->update([
            'estado' => 'pagado',
            'paid_at' => $kardex->fecha_pago,
        ]);

        $this->cuotasActualizadas++;

        Log::info("✅ PASO 5 EXITOSO: Cuota marcada como pagada", [
            'cuota_id' => $cuota->id,
            'numero_cuota' => $cuota->numero_cuota,
            'estado_nuevo' => 'pagado',
            'paid_at' => $kardex->fecha_pago->toDateString()
        ]);

        if (abs($diferencia) > 100) {
            Log::info("💰 Cuota actualizada con diferencia", [
                'cuota_id' => $cuota->id,
                'monto_cuota' => $cuota->monto,
                'monto_pagado' => $montoPago,
                'diferencia' => round($diferencia, 2),
                'tipo' => $diferencia > 0 ? 'PAGO_MENOR' : 'SOBREPAGO'
            ]);
        }

        Log::info("🔍 PASO 6: Creando registro de conciliación", [
            'kardex_id' => $kardex->id,
            'banco' => $banco,
            'boleta' => $kardex->numero_boleta,
            'monto' => $kardex->monto_pagado
        ]);

        $bancoNormalizado = $this->normalizeBank($banco);
        $boletaNormalizada = $this->normalizeReceiptNumber($kardex->numero_boleta);
        $fechaYmd = Carbon::parse($kardex->fecha_pago)->format('Y-m-d');
        $fingerprint = $this->makeFingerprint($bancoNormalizado, $boletaNormalizada, $kardex->monto_pagado, $fechaYmd);

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
                'kardex_pago_id' => $kardex->id,
                'uploaded_by' => $this->uploaderId, // 👈 ESTE ES EL FIX
            ]);

            $this->conciliaciones++;

            Log::info("✅ PASO 6 EXITOSO: Conciliación creada", [
                'kardex_id' => $kardex->id,
                'fingerprint' => $fingerprint,
                'status' => 'conciliado',
                'uploaded_by' => $this->uploaderId
            ]);
        } catch (\Throwable $e) {
            Log::error("❌ PASO 6 FALLIDO: Error creando conciliación", [
                'error' => $e->getMessage(),
                'kardex_id' => $kardex->id,
                'fingerprint' => $fingerprint,
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 3)
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
        if ($programas->count() === 1) {
            return $programas->first();
        }

        Log::info("🔍 Identificando programa entre {$programas->count()} opciones", [
            'mensualidad_aprobada' => $mensualidadAprobada,
            'monto_pago' => $montoPago,
            'fecha_pago' => $fechaPago->toDateString()
        ]);

        // 🔥 PRIORIDAD 1: Por mensualidad aprobada con tolerancia del 50%
        if ($mensualidadAprobada > 0) {
            foreach ($programas as $programa) {
                $cuotasPrograma = $this->obtenerCuotasDelPrograma($programa->estudiante_programa_id);

                // Si hay cuotas, intentar coincidir por monto
                if ($cuotasPrograma->isNotEmpty()) {
                    $cuotaCoincidente = $cuotasPrograma->first(function ($cuota) use ($mensualidadAprobada) {
                        $diferencia = abs($cuota->monto - $mensualidadAprobada);
                        $tolerancia = max(100, $mensualidadAprobada * 0.50);
                        return $diferencia <= $tolerancia;
                    });

                    if ($cuotaCoincidente) {
                        Log::info("✅ Programa identificado por mensualidad aprobada (cuotas)", [
                            'estudiante_programa_id' => $programa->estudiante_programa_id,
                            'programa' => $programa->nombre_programa,
                            'mensualidad' => $mensualidadAprobada,
                            'cuota_monto' => $cuotaCoincidente->monto
                        ]);
                        return $programa;
                    }
                } else {
                    // 🔥 NUEVO: Si no hay cuotas, usar precio del programa
                    $precioPrograma = $this->obtenerPrecioPrograma($programa->estudiante_programa_id);
                    if ($precioPrograma) {
                        $diferencia = abs($precioPrograma->cuota_mensual - $mensualidadAprobada);
                        $tolerancia = max(100, $mensualidadAprobada * 0.50);

                        if ($diferencia <= $tolerancia) {
                            Log::info("✅ Programa identificado por mensualidad aprobada (precio programa)", [
                                'estudiante_programa_id' => $programa->estudiante_programa_id,
                                'programa' => $programa->nombre_programa,
                                'mensualidad' => $mensualidadAprobada,
                                'cuota_mensual_programa' => $precioPrograma->cuota_mensual
                            ]);
                            return $programa;
                        }
                    }
                }
            }
        }

        // 🔥 PRIORIDAD 2: Por rango de fechas
        foreach ($programas as $programa) {
            $cuotasPrograma = $this->obtenerCuotasDelPrograma($programa->estudiante_programa_id);

            if ($cuotasPrograma->isEmpty()) {
                continue;
            }

            $primeraFecha = $cuotasPrograma->min('fecha_vencimiento');
            $ultimaFecha = $cuotasPrograma->max('fecha_vencimiento');

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

        // 🔥 PRIORIDAD 3: Por monto de pago con tolerancia del 50%
        foreach ($programas as $programa) {
            $cuotasPrograma = $this->obtenerCuotasDelPrograma($programa->estudiante_programa_id);

            $cuotaCoincidente = $cuotasPrograma->first(function ($cuota) use ($montoPago) {
                $diferencia = abs($cuota->monto - $montoPago);
                $tolerancia = max(100, $cuota->monto * 0.50);
                return $diferencia <= $tolerancia;
            });

            if ($cuotaCoincidente) {
                Log::info("✅ Programa identificado por monto de pago", [
                    'estudiante_programa_id' => $programa->estudiante_programa_id,
                    'programa' => $programa->nombre_programa,
                    'monto_pago' => $montoPago
                ]);
                return $programa;
            }
        }

        // 🔥 PRIORIDAD 4: Programa con más cuotas pendientes (probablemente el activo)
        $programaConMasCuotas = $programas->sortByDesc(function ($programa) {
            return $this->obtenerCuotasDelPrograma($programa->estudiante_programa_id)
                ->where('estado', 'pendiente')
                ->count();
        })->first();

        if ($programaConMasCuotas) {
            $cuotasPendientes = $this->obtenerCuotasDelPrograma($programaConMasCuotas->estudiante_programa_id)
                ->where('estado', 'pendientes')
                ->count();

            if ($cuotasPendientes > 0) {
                Log::info("✅ Programa identificado por mayor cantidad de cuotas pendientes", [
                    'estudiante_programa_id' => $programaConMasCuotas->estudiante_programa_id,
                    'programa' => $programaConMasCuotas->nombre_programa,
                    'cuotas_pendientes' => $cuotasPendientes
                ]);
                return $programaConMasCuotas;
            }
        }

        // 🔥 PRIORIDAD 5: Usar el más reciente (última opción)
        Log::warning("⚠️ No se pudo identificar programa específico, usando el más reciente", [
            'programas_disponibles' => $programas->count()
        ]);
        return $programas->first();
    }

    /**
     * 🔥 MÉTODO MEJORADO: Ahora crea estudiantes/programas si no existen
     */
    private function obtenerProgramasEstudiante($carnet, $row = null)
    {
        if (isset($this->estudiantesCache[$carnet])) {
            Log::debug("📋 Usando cache para carnet", ['carnet' => $carnet]);
            return $this->estudiantesCache[$carnet];
        }

        Log::info("🔍 PASO 1: Buscando prospecto por carnet", ['carnet' => $carnet]);

        $prospecto = DB::table('prospectos')
            ->where('carnet', '=', $carnet)
            ->first();

        // 🔥 NUEVO: Si no existe prospecto, crearlo
        if (!$prospecto && $row) {
            Log::warning("❌ Prospecto no encontrado, creando desde datos de pago", [
                'carnet' => $carnet
            ]);

            try {
                // Convert Collection to array if needed
                $rowArray = $row instanceof Collection ? $row->toArray() : $row;
                $programaCreado = $this->estudianteService->syncEstudianteFromPaymentRow($rowArray, $this->uploaderId);

                if ($programaCreado) {
                    $this->estudiantesCache[$carnet] = collect([$programaCreado]);
                    return collect([$programaCreado]);
                }
            } catch (\Throwable $e) {
                Log::warning("⚠️ No se pudo crear prospecto automáticamente", [
                    'carnet' => $carnet,
                    'error' => $e->getMessage()
                ]);
                $this->estudiantesCache[$carnet] = collect([]);
                return collect([]);
            }

            // Si aún falla, retornar vacío
            $this->estudiantesCache[$carnet] = collect([]);
            return collect([]);
        }

        if (!$prospecto) {
            Log::warning("❌ PASO 1 FALLIDO: Prospecto no encontrado y no se pudo crear", [
                'carnet' => $carnet
            ]);
            $this->estudiantesCache[$carnet] = collect([]);
            return collect([]);
        }

        Log::info("✅ PASO 1 EXITOSO: Prospecto encontrado", [
            'carnet' => $carnet,
            'prospecto_id' => $prospecto->id,
            'nombre_completo' => $prospecto->nombre_completo
        ]);

        Log::info("🔍 PASO 2: Buscando programas del estudiante", [
            'prospecto_id' => $prospecto->id
        ]);

        $estudianteProgramas = DB::table('estudiante_programa')
            ->where('prospecto_id', $prospecto->id)
            ->get();

        // 🔥 NUEVO: Si no tiene programas, crear con datos del Excel
        if ($estudianteProgramas->isEmpty() && $row) {
            Log::warning("❌ No hay programas, creando desde datos de pago", [
                'prospecto_id' => $prospecto->id
            ]);

            try {
                // Convert Collection to array if needed
                $rowArray = $row instanceof Collection ? $row->toArray() : $row;
                $programaCreado = $this->estudianteService->syncEstudianteFromPaymentRow($rowArray, $this->uploaderId);

                if ($programaCreado) {
                    $this->estudiantesCache[$carnet] = collect([$programaCreado]);
                    return collect([$programaCreado]);
                }
            } catch (\Throwable $e) {
                Log::warning("⚠️ No se pudo crear prospecto automáticamente", [
                    'carnet' => $carnet,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if ($estudianteProgramas->isEmpty()) {
            Log::warning("❌ PASO 2 FALLIDO: No hay programas para este prospecto", [
                'carnet' => $carnet,
                'prospecto_id' => $prospecto->id
            ]);
            $this->estudiantesCache[$carnet] = collect([]);
            return collect([]);
        }

        Log::info("✅ PASO 2 EXITOSO: Programas encontrados", [
            'prospecto_id' => $prospecto->id,
            'cantidad_programas' => $estudianteProgramas->count()
        ]);

        $programas = DB::table('prospectos as p')
            ->select(
                'p.id as prospecto_id',
                'p.carnet',
                'p.nombre_completo',
                'ep.id as estudiante_programa_id',
                'ep.programa_id',
                'ep.created_at as fecha_inscripcion',
                'prog.nombre_del_programa as nombre_programa',
                'prog.abreviatura as programa_abreviatura',
                'prog.activo as programa_activo'
            )
            ->join('estudiante_programa as ep', 'p.id', '=', 'ep.prospecto_id')
            ->leftJoin('tb_programas as prog', 'ep.programa_id', '=', 'prog.id')
            ->where(DB::raw("REPLACE(UPPER(p.carnet), ' ', '')"), '=', $carnet)
            ->orderBy('ep.created_at', 'desc')
            ->get();

        // 🔥 NUEVO: Actualizar programas TEMP a reales si el Excel tiene plan_estudios
        if ($row && !empty($row['plan_estudios'])) {
            foreach ($programas as $programa) {
                if ($programa->programa_abreviatura === 'TEMP') {
                    Log::info("🔄 Detectado programa TEMP, intentando actualizar", [
                        'estudiante_programa_id' => $programa->estudiante_programa_id,
                        'plan_estudios_excel' => $row['plan_estudios']
                    ]);

                    $actualizado = $this->estudianteService->actualizarProgramaTempAReal(
                        $programa->estudiante_programa_id,
                        $row['plan_estudios'],
                        $this->uploaderId
                    );

                    if ($actualizado) {
                        // Recargar programas después de actualizar
                        unset($this->estudiantesCache[$carnet]);
                        return $this->obtenerProgramasEstudiante($carnet, $row);
                    }
                }
            }
        }

        // 🔥 NUEVO: Generar cuotas si no existen
        foreach ($programas as $programa) {
            // Convert Collection to array if needed
            $rowArray = $row instanceof Collection ? $row->toArray() : $row;
            $this->generarCuotasSiFaltan($programa->estudiante_programa_id, $rowArray);
        }

        $this->estudiantesCache[$carnet] = $programas;

        return $programas;
    }

    private function obtenerCuotasDelPrograma(int $estudianteProgramaId)
    {
        if (isset($this->cuotasPorEstudianteCache[$estudianteProgramaId])) {
            Log::debug("📋 Usando cache para cuotas", ['estudiante_programa_id' => $estudianteProgramaId]);
            return $this->cuotasPorEstudianteCache[$estudianteProgramaId];
        }

        Log::info("🔍 PASO 4: Buscando cuotas del programa", [
            'estudiante_programa_id' => $estudianteProgramaId
        ]);

        $cuotas = CuotaProgramaEstudiante::where('estudiante_programa_id', $estudianteProgramaId)
            ->orderBy('fecha_vencimiento', 'asc')
            ->get();

        if ($cuotas->isEmpty()) {
            Log::warning("❌ PASO 4: No hay cuotas para este programa", [
                'estudiante_programa_id' => $estudianteProgramaId,
                'problema' => 'No existen cuotas en cuotas_programa_estudiante para este estudiante_programa_id'
            ]);
        } else {
            $pendientes = $cuotas->where('estado', 'pendiente')->count();
            $pagadas = $cuotas->where('estado', 'pagado')->count();

            Log::info("✅ PASO 4 EXITOSO: Cuotas encontradas", [
                'estudiante_programa_id' => $estudianteProgramaId,
                'total_cuotas' => $cuotas->count(),
                'cuotas_pendientes' => $pendientes,
                'cuotas_pagadas' => $pagadas,
                'resumen_cuotas' => $cuotas->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'numero' => $c->numero_cuota,
                        'monto' => $c->monto,
                        'estado' => $c->estado,
                        'vencimiento' => $c->fecha_vencimiento
                    ];
                })->toArray()
            ]);
        }

        $this->cuotasPorEstudianteCache[$estudianteProgramaId] = $cuotas;

        return $cuotas;
    }

    /**
     * 🆕 Obtener precio estándar del programa
     * Útil cuando no existen cuotas creadas para validar montos
     */
    private function obtenerPrecioPrograma(int $estudianteProgramaId)
    {
        try {
            // Obtener programa_id del estudiante_programa
            $estudiantePrograma = DB::table('estudiante_programa')
                ->where('id', $estudianteProgramaId)
                ->first();

            if (!$estudiantePrograma) {
                return null;
            }

            // Buscar el precio del programa
            $precioPrograma = PrecioPrograma::where('programa_id', $estudiantePrograma->programa_id)->first();

            if ($precioPrograma) {
                Log::debug("💰 Precio de programa encontrado", [
                    'estudiante_programa_id' => $estudianteProgramaId,
                    'programa_id' => $estudiantePrograma->programa_id,
                    'cuota_mensual' => $precioPrograma->cuota_mensual,
                    'inscripcion' => $precioPrograma->inscripcion,
                    'meses' => $precioPrograma->meses
                ]);
            }

            return $precioPrograma;
        } catch (\Throwable $ex) {
            Log::warning("⚠️ Error al obtener precio de programa", [
                'estudiante_programa_id' => $estudianteProgramaId,
                'error' => $ex->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 🆕 Generar cuotas automáticamente cuando no existen
     * Similar a la lógica de InscripcionesImport
     * 
     * Mejoras:
     * - Soporte para programas TEMP con cuotas dinámicas
     * - Generación de cuota 0 (inscripción) si aplica
     * - Inferencia de cantidad de cuotas desde pagos del Excel
     */
    private function generarCuotasSiFaltan(int $estudianteProgramaId, ?array $row = null)
    {
        try {
            // Verificar si ya existen cuotas
            $cuotasExistentes = DB::table('cuotas_programa_estudiante')
                ->where('estudiante_programa_id', $estudianteProgramaId)
                ->count();

            if ($cuotasExistentes > 0) {
                Log::debug("⏭️ Ya existen cuotas para este programa, saltando generación", [
                    'estudiante_programa_id' => $estudianteProgramaId,
                    'cuotas_existentes' => $cuotasExistentes
                ]);
                return false;
            }

            // Obtener datos del estudiante_programa y programa
            $estudiantePrograma = DB::table('estudiante_programa as ep')
                ->leftJoin('tb_programas as prog', 'ep.programa_id', '=', 'prog.id')
                ->where('ep.id', $estudianteProgramaId)
                ->select('ep.*', 'prog.abreviatura as programa_codigo')
                ->first();

            if (!$estudiantePrograma) {
                Log::warning("⚠️ No se encontró estudiante_programa", [
                    'estudiante_programa_id' => $estudianteProgramaId
                ]);
                return false;
            }

            // Detectar si es programa TEMP
            $esProgramaTemp = strtoupper($estudiantePrograma->programa_codigo ?? '') === 'TEMP';

            // Usar datos del estudiante_programa para generar cuotas
            $numCuotas = $estudiantePrograma->duracion_meses ?? 0;
            $cuotaMensual = $estudiantePrograma->cuota_mensual ?? 0;
            $fechaInicio = $estudiantePrograma->fecha_inicio ?? now()->toDateString();
            $inscripcion = null;

            // 🔥 NUEVO: Para programas TEMP, inferir cantidad de cuotas desde Excel
            if ($esProgramaTemp && $row) {
                // Intentar inferir el número de cuotas desde el número de pagos
                // Esto requeriría conocer todos los pagos del estudiante
                Log::info("🧮 Programa TEMP detectado, usando configuración dinámica", [
                    'estudiante_programa_id' => $estudianteProgramaId,
                    'programa_codigo' => $estudiantePrograma->programa_codigo
                ]);
                
                // Para TEMP, usar valores por defecto si no hay datos
                if ($numCuotas <= 0) {
                    $numCuotas = 12; // Default razonable para TEMP
                }
                if ($cuotaMensual <= 0 && isset($row['mensualidad_aprobada'])) {
                    $cuotaMensual = $this->normalizarMonto($row['mensualidad_aprobada']);
                }
            }

            // Si no hay datos suficientes en estudiante_programa, intentar con precio_programa
            if ($numCuotas <= 0 || $cuotaMensual <= 0) {
                $precioPrograma = $this->obtenerPrecioPrograma($estudianteProgramaId);
                if ($precioPrograma) {
                    $numCuotas = $numCuotas > 0 ? $numCuotas : ($precioPrograma->meses ?? 12);
                    $cuotaMensual = $cuotaMensual > 0 ? $cuotaMensual : ($precioPrograma->cuota_mensual ?? 0);
                    
                    // 🆕 Obtener inscripción si está disponible
                    if ($precioPrograma->inscripcion > 0) {
                        $inscripcion = $precioPrograma->inscripcion;
                    }
                }
            }

            // 🆕 Inferir inscripción desde Excel si está disponible
            if (!$inscripcion && $row && isset($row['inscripcion']) && $row['inscripcion'] > 0) {
                $inscripcion = $this->normalizarMonto($row['inscripcion']);
            }

            // Validar que tengamos los datos mínimos
            if ($numCuotas <= 0 || $cuotaMensual <= 0) {
                Log::warning("⚠️ No se pueden generar cuotas: datos insuficientes", [
                    'estudiante_programa_id' => $estudianteProgramaId,
                    'num_cuotas' => $numCuotas,
                    'cuota_mensual' => $cuotaMensual,
                    'programa_codigo' => $estudiantePrograma->programa_codigo ?? 'N/A'
                ]);
                return false;
            }

            Log::info("🔧 Generando cuotas automáticamente", [
                'estudiante_programa_id' => $estudianteProgramaId,
                'num_cuotas' => $numCuotas,
                'cuota_mensual' => $cuotaMensual,
                'inscripcion' => $inscripcion,
                'fecha_inicio' => $fechaInicio,
                'es_temp' => $esProgramaTemp
            ]);

            // Generar las cuotas
            $cuotas = [];
            
            // 🆕 CUOTA 0 (Inscripción) si aplica
            if ($inscripcion && $inscripcion > 0) {
                $cuotas[] = [
                    'estudiante_programa_id' => $estudianteProgramaId,
                    'numero_cuota' => 0,
                    'fecha_vencimiento' => $fechaInicio,
                    'monto' => $inscripcion,
                    'estado' => 'pendiente',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                
                Log::info("✅ Cuota 0 (Inscripción) agregada", [
                    'monto' => $inscripcion
                ]);
            }
            
            // Cuotas 1..N (cuotas mensuales)
            for ($i = 1; $i <= $numCuotas; $i++) {
                $fechaVencimiento = Carbon::parse($fechaInicio)
                    ->addMonths($i - 1)
                    ->toDateString();

                $cuotas[] = [
                    'estudiante_programa_id' => $estudianteProgramaId,
                    'numero_cuota' => $i,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'monto' => $cuotaMensual,
                    'estado' => 'pendiente',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insertar las cuotas en la base de datos
            DB::table('cuotas_programa_estudiante')->insert($cuotas);

            Log::info("✅ Cuotas generadas exitosamente", [
                'estudiante_programa_id' => $estudianteProgramaId,
                'cantidad_cuotas' => count($cuotas),
                'incluye_inscripcion' => $inscripcion ? 'SÍ' : 'NO'
            ]);

            // Limpiar cache para forzar recarga
            unset($this->cuotasPorEstudianteCache[$estudianteProgramaId]);

            return true;
        } catch (\Throwable $ex) {
            Log::error("❌ Error al generar cuotas automáticas", [
                'estudiante_programa_id' => $estudianteProgramaId,
                'error' => $ex->getMessage(),
                'trace' => $ex->getTraceAsString()
            ]);
            return false;
        }
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
            if (is_numeric($fecha)) {
                $baseDate = Carbon::create(1899, 12, 30);
                $resultado = $baseDate->addDays(intval($fecha));

                Log::debug('📅 Fecha normalizada desde Excel', [
                    'original' => $fecha,
                    'resultado' => $resultado->toDateString()
                ]);

                return $resultado;
            }

            if (empty($fecha) || trim($fecha) === '') {
                Log::debug('⚠️ Fecha vacía detectada');
                return null;
            }

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

    /**
     * Get a human-readable description for error types
     */
    private function getErrorTypeDescription(string $tipo): string
    {
        $descriptions = [
            'ERROR_PROCESAMIENTO_ESTUDIANTE' => 'Error crítico al procesar estudiante (posible error de tipo de datos o configuración)',
            'ERROR_PROCESAMIENTO_PAGO' => 'Error al procesar un pago individual',
            'ESTUDIANTE_NO_ENCONTRADO' => 'No se encontró el estudiante en el sistema y no se pudo crear',
            'PROGRAMA_NO_IDENTIFICADO' => 'No se pudo identificar o crear el programa del estudiante',
            'DATOS_INCOMPLETOS' => 'Faltan datos requeridos en la fila del Excel',
            'ARCHIVO_VACIO' => 'El archivo Excel no contiene datos',
            'ESTRUCTURA_INVALIDA' => 'El archivo no tiene la estructura de columnas esperada'
        ];

        return $descriptions[$tipo] ?? 'Error no categorizado';
    }
}
