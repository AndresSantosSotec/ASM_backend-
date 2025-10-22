<?php

namespace App\Imports;

// ✅ AGREGAR ESTAS LÍNEAS AL INICIO
ini_set('memory_limit', '2048M'); //
ini_set('max_execution_time', '1500'); //

use App\Services\EstudianteService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\AdicionalEstudiante;
use App\Models\KardexPago;
use App\Models\CuotaProgramaEstudiante;
use App\Models\ReconciliationRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

    // 🆕 Métricas de duplicados permitidos
    public int $duplicadosPermitidos = 0;

    private array $estudiantesCache = [];
    private array $cuotasPorEstudianteCache = [];
    private array $adicionalEstudianteCache = [];
    private ?string $columnaMensualidad = null;

    private ?bool $kardexTienePeriodoPago = null;

    // 🆕 NUEVO: Servicio de estudiantes
    private EstudianteService $estudianteService;

    public function __construct(int $uploaderId, string $tipoArchivo = 'cardex_directo')
    {
        $this->uploaderId = $uploaderId;
        $this->tipoArchivo = $tipoArchivo;
        $this->estudianteService = new EstudianteService();

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

        if ($this->columnaMensualidad) {
            Log::info('📐 Columna de mensualidad detectada', [
                'columna' => $this->columnaMensualidad,
            ]);
        } else {
            Log::warning('⚠️ Columna de mensualidad no encontrada, se asumirá valor 0 para coincidencias de cuota');
        }

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

        $reporteFinal = [
            'timestamp' => now()->toIso8601String(),
            'total_duplicados_permitidos' => $this->duplicadosPermitidos,
            'total_errores_criticos' => count($this->errores),
        ];

        Log::info('📋 RESUMEN FINAL DE VALIDACIÓN DE MIGRACIÓN', $reporteFinal);

        if ($this->duplicadosPermitidos > 0) {
            Log::warning('⚠️ Incidencias controladas durante la migración', [
                'duplicados_permitidos' => $this->duplicadosPermitidos,
            ]);
        }

        $this->guardarResumenMigracion($reporteFinal);

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
                        'ejemplos' => $errores->take(3)->map(function($error) {
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
                    'primeros_5_casos' => $errores->take(5)->map(function($error) {
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
            'plan_estudios',
            'estatus',
            'numero_boleta',
            'monto',
            'fecha_pago',
            'banco',
            'concepto',
            'tipo_pago',
            'mes_pago',
            'ano',
        ];

        $columnasOpcionales = [
            'mes_inicio',
            'fila_origen',
            'mensualidad_aprobada',
            'mensualidad',
            'notas_pago',
            'asesor',
            'empresa_donde_labora',
            'telefono',
            'mail',
            'nomenclatura'
        ];

        if (!$primeraFila) {
            return [
                'valido' => false,
                'faltantes' => $columnasRequeridas,
                'encontradas' => []
            ];
        }

        $columnasEncontradas = array_keys($primeraFila->toArray());
        $normalizadasEncontradas = [];
        foreach ($columnasEncontradas as $columna) {
            $normalizadasEncontradas[$this->normalizarClave($columna)] = $columna;
        }

        $faltantes = [];
        foreach ($columnasRequeridas as $columnaRequerida) {
            $clave = $this->normalizarClave($columnaRequerida);
            if (!array_key_exists($clave, $normalizadasEncontradas)) {
                $faltantes[] = $columnaRequerida;
            }
        }

        // Detectar columna de mensualidad (opcional)
        $this->columnaMensualidad = null;
        foreach ([
            'mensualidad_aprobada',
            'mensualidad',
            'mensualidad aprobada',
            'mensualidad_aprobado',
        ] as $aliasMensualidad) {
            $clave = $this->normalizarClave($aliasMensualidad);
            if (isset($normalizadasEncontradas[$clave])) {
                $this->columnaMensualidad = $normalizadasEncontradas[$clave];
                break;
            }
        }

        $opcionalesEncontradas = [];
        foreach ($columnasOpcionales as $opcional) {
            $clave = $this->normalizarClave($opcional);
            if (isset($normalizadasEncontradas[$clave])) {
                $opcionalesEncontradas[] = $normalizadasEncontradas[$clave];
            }
        }

        return [
            'valido' => empty($faltantes),
            'faltantes' => $faltantes,
            'encontradas' => $columnasEncontradas,
            'opcionales_encontradas' => $opcionalesEncontradas,
            'columna_mensualidad_detectada' => $this->columnaMensualidad,
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

        $this->guardarInformacionAdicionalEstudiante($carnetNormalizado, $pagos);

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

    private function guardarInformacionAdicionalEstudiante(string $carnet, Collection $pagos): void
    {
        if (isset($this->adicionalEstudianteCache[$carnet])) {
            return;
        }

        $notasPago = $pagos
            ->pluck('notas_pago')
            ->map(fn ($valor, $key) => trim((string) $valor))
            ->first(fn ($valor, $key) => $valor !== '');

        $nomenclatura = $pagos
            ->pluck('nomenclatura')
            ->map(fn ($valor, $key) => trim((string) $valor))
            ->first(fn ($valor, $key) => $valor !== '');

        if ($notasPago === null && $nomenclatura === null) {
            $this->adicionalEstudianteCache[$carnet] = true;
            return;
        }

        $registro = AdicionalEstudiante::firstOrNew(['carnet' => $carnet]);
        $cambios = false;

        if ($notasPago !== null && $notasPago !== '' && $registro->notas_pago !== $notasPago) {
            $registro->notas_pago = $notasPago;
            $cambios = true;
        }

        if ($nomenclatura !== null && $nomenclatura !== '' && $registro->nomenclatura !== $nomenclatura) {
            $registro->nomenclatura = $nomenclatura;
            $cambios = true;
        }

        if (!$registro->exists || $cambios) {
            $registro->save();

            Log::info('🆕 Información adicional de estudiante registrada/actualizada', [
                'carnet' => $carnet,
                'notas_pago' => $registro->notas_pago,
                'nomenclatura' => $registro->nomenclatura,
            ]);
        }

        $this->adicionalEstudianteCache[$carnet] = true;
    }

    private function procesarPagoIndividual($row, Collection $programasEstudiante, $numeroFila)
    {
        $rowArray = $row instanceof Collection ? $row->toArray() : $row;

        // ✅ Extraer y normalizar datos
        $carnet = $this->normalizarCarnet((string) $this->obtenerValorFila($rowArray, ['carnet'], ''));
        $nombreEstudiante = trim((string) $this->obtenerValorFila($rowArray, ['nombre_estudiante'], ''));
        $boleta = $this->normalizarBoleta($this->obtenerValorFila($rowArray, ['numero_boleta'], ''));
        $monto = $this->normalizarMonto($this->obtenerValorFila($rowArray, ['monto'], 0));
        $fechaPago = $this->normalizarFecha($this->obtenerValorFila($rowArray, ['fecha_pago'], null));
        $bancoRaw = trim((string) $this->obtenerValorFila($rowArray, ['banco'], ''));
        $banco = $bancoRaw === '' ? 'EFECTIVO' : $bancoRaw;
        $concepto = trim((string) $this->obtenerValorFila($rowArray, ['concepto'], 'Cuota mensual'));
        $mesPago = trim((string) $this->obtenerValorFila($rowArray, ['mes_pago'], ''));
        $planEstudios = trim((string) $this->obtenerValorFila($rowArray, ['plan_estudios', 'plan_estudio', 'plan'], ''));
        $tipoPagoOriginal = trim((string) $this->obtenerValorFila($rowArray, ['tipo_pago'], 'MENSUAL'));
        $tipoPagoNormalizado = strtoupper($tipoPagoOriginal);
        $ano = trim((string) $this->obtenerValorFila($rowArray, ['año', 'ano'], ''));
        $estatus = trim((string) $this->obtenerValorFila($rowArray, ['estatus'], ''));

        $periodoPagoInfo = $this->obtenerPeriodoDesdeFila($rowArray, $fechaPago);
        $periodoPagoString = $this->formatearPeriodo($periodoPagoInfo['mes'], $periodoPagoInfo['anio']);

        Log::info("📄 Procesando fila {$numeroFila}", [
            'carnet' => $carnet,
            'nombre' => $nombreEstudiante,
            'boleta' => $boleta,
            'monto' => $monto,
            'fecha_pago' => $fechaPago?->toDateString(),
            'tipo_pago' => $tipoPagoNormalizado,
            'estatus' => $estatus,
            'plan_estudios' => $planEstudios,
            'mes_pago' => $mesPago,
            'ano' => $ano,
            'periodo_mes_normalizado' => $periodoPagoInfo['mes'],
            'periodo_anio_normalizado' => $periodoPagoInfo['anio'],
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
            $monto,
            $fechaPago,
            $rowArray
        );

        if (!$programaAsignado) {
            $this->errores[] = [
                'tipo' => 'PROGRAMA_NO_IDENTIFICADO',
                'fila' => $numeroFila,
                'carnet' => $carnet,
                'error' => 'No se pudo identificar el programa correcto para este pago',
                'programas_disponibles' => $programasEstudiante->count(),
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
                $nombreEstudiante,
                $tipoPagoNormalizado,
                $tipoPagoOriginal,
                $ano,
                $planEstudios,
                $estatus,
                $rowArray,
                $periodoPagoString,
                $periodoPagoInfo
            ) {
                // ✅ Verificar duplicados para permitirlos cuando provienen del Excel
                $duplicadoPermitido = false;
                $contextosDuplicado = [];

                $kardexExistente = KardexPago::where('numero_boleta', $boleta)
                    ->where('estudiante_programa_id', $programaAsignado->estudiante_programa_id)
                    ->first();

                if ($kardexExistente) {
                    Log::info("⚠️ Kardex duplicado detectado (por boleta+estudiante) - marcado como permitido", [
                        'kardex_id' => $kardexExistente->id,
                        'boleta' => $boleta,
                        'estudiante_programa_id' => $programaAsignado->estudiante_programa_id
                    ]);

                    $contextosDuplicado[] = [
                        'motivo' => 'boleta',
                        'kardex_id_existente' => $kardexExistente->id,
                    ];
                }

                $bancoNormalizado = $this->normalizeBank($banco);
                $boletaNormalizada = $this->normalizeReceiptNumber($boleta);
                $fechaYmd = $fechaPago->format('Y-m-d');
                $fingerprint = hash('sha256',
                    $bancoNormalizado.'|'.$boletaNormalizada.'|'.$programaAsignado->estudiante_programa_id.'|'.$fechaYmd);

                $kardexPorFingerprint = KardexPago::where('boleta_fingerprint', $fingerprint)->first();

                if ($kardexPorFingerprint) {
                    Log::info("⚠️ Kardex duplicado detectado (por fingerprint) - marcado como permitido", [
                        'kardex_id' => $kardexPorFingerprint->id,
                        'fingerprint' => $fingerprint,
                        'boleta' => $boleta,
                        'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
                        'fecha_pago' => $fechaYmd
                    ]);

                    $contextosDuplicado[] = [
                        'motivo' => 'fingerprint',
                        'kardex_id_existente' => $kardexPorFingerprint->id,
                        'fingerprint' => substr($fingerprint, 0, 16).'...'
                    ];
                }

                if (!empty($contextosDuplicado)) {
                    if ($this->tipoArchivo === 'cardex_directo') {
                        $duplicadoPermitido = true;
                        $this->duplicadosPermitidos++;

                        foreach ($contextosDuplicado as $contextoDuplicado) {
                            $this->registrarDuplicadoPermitido(array_merge($contextoDuplicado, [
                                'fila' => $numeroFila,
                                'boleta' => $boleta,
                                'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
                            ]));
                        }
                    } else {
                        Log::error('❌ Pago duplicado detectado en importación no permitida', [
                            'tipo_archivo' => $this->tipoArchivo,
                            'fila' => $numeroFila,
                            'boleta' => $boleta,
                            'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
                        ]);

                        throw new \RuntimeException('Pago duplicado detectado y no permitido para tipo_archivo ' . $this->tipoArchivo);
                    }
                }

                // 🔥 Buscar cuota con lógica flexible
                // 🆕 NUEVO: Solo asignar cuota si el tipo_pago es "MENSUAL"
                $cuota = null;
                $esMenual = $this->esPagoMensual($tipoPagoNormalizado);

                if ($esMenual) {
                    Log::info("🔍 Buscando cuota para asignar al pago (MENSUAL)", [
                        'fila' => $numeroFila,
                        'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
                        'fecha_pago' => $fechaPago->toDateString(),
                        'monto' => $monto,
                        'tipo_pago' => $tipoPagoNormalizado
                    ]);

                    $cuota = $this->buscarCuotaFlexible(
                        $programaAsignado->estudiante_programa_id,
                        $rowArray,
                        $fechaPago,
                        $monto,
                        $numeroFila
                    );
                } else {
                    Log::info("⏭️ Saltando asignación de cuota (pago NO es mensual)", [
                        'fila' => $numeroFila,
                        'tipo_pago' => $tipoPagoNormalizado,
                        'estudiante_programa_id' => $programaAsignado->estudiante_programa_id
                    ]);
                }

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

                $periodoDescripcion = $periodoPagoString ?? 'SIN_PERIODO';
                $observaciones = sprintf(
                    "%s | Estudiante: %s | Mes: %s | Año: %s | Periodo normalizado: %s | Tipo: %s | Migración fila %d | Programa: %s",
                    $concepto,
                    $nombreEstudiante,
                    $mesPago,
                    $ano !== '' ? $ano : $fechaPago->format('Y'),
                    $periodoDescripcion,
                    $tipoPagoOriginal,
                    $numeroFila,
                    $programaAsignado->nombre_programa ?? 'N/A'
                );

                $kardexData = [
                    'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
                    'cuota_id' => $cuota ? $cuota->id : null,
                    'numero_boleta' => $boleta,
                    'monto_pagado' => $monto,
                    'fecha_pago' => $fechaPago,
                    'fecha_recibo' => $fechaPago,
                    'banco' => $banco,
                    'estado_pago' => 'aprobado',
                    'observaciones' => $observaciones,
                ];

                if ($periodoPagoString && $this->kardexSoportaPeriodoPago()) {
                    $kardexData['periodo_pago'] = $periodoPagoString;
                }

                $kardexModel = new KardexPago($kardexData);

                if ($duplicadoPermitido) {
                    $kardexModel->permitirDuplicadoExcel = true;
                }

                $kardexModel->save();
                $kardex = $kardexModel;

                $this->kardexCreados++;
                $this->totalAmount += $monto;

                Log::info("✅ Kardex creado exitosamente", [
                    'kardex_id' => $kardex->id,
                    'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
                    'cuota_id' => $cuota ? $cuota->id : 'SIN CUOTA',
                    'programa' => $programaAsignado->nombre_programa ?? 'N/A',
                    'numero_boleta' => $boleta,
                    'monto' => $monto,
                    'fila' => $numeroFila,
                    'periodo_pago' => $periodoPagoString
                ]);

                // ✅ Actualizar cuota y conciliar si existe cuota
                if ($cuota) {
                    $this->actualizarCuotaYConciliar($cuota, $kardex, $numeroFila, $monto);
                } else {
                    Log::info("⏭️ Saltando actualización de cuota (no se asignó cuota)", [
                        'kardex_id' => $kardex->id,
                        'fila' => $numeroFila
                    ]);

                    if ($this->tipoArchivo === 'cardex_directo') {
                        Log::info('🔄 Creando conciliación para kardex sin cuota (importación directa)', [
                            'kardex_id' => $kardex->id,
                            'fila' => $numeroFila,
                            'motivo' => 'cardex_directo_sin_cuota'
                        ]);

                        $this->crearConciliacionDesdeKardex($kardex, 'sin_cuota_cardex_directo');
                    }
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
                    'fecha_pago' => $fechaPago->toDateString(),
                    'tipo_pago' => $tipoPagoOriginal,
                    'plan_estudios' => $planEstudios,
                    'estatus' => $estatus,
                    'mes_pago' => $mesPago,
                    'ano' => $ano !== '' ? $ano : $fechaPago->format('Y'),
                    'periodo_pago' => $periodoPagoString,
                    'periodo_mes_normalizado' => $periodoPagoInfo['mes'],
                    'periodo_anio_normalizado' => $periodoPagoInfo['anio'],
                    'duplicado_permitido' => $duplicadoPermitido,
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

    private function registrarDuplicadoPermitido(array $contexto): void
    {
        $detalle = array_merge([
            'tipo' => 'DUPLICADO_PERMITIDO',
            'advertencia' => 'Duplicado permitido (fuente Excel)',
            'fuente' => 'excel',
            'accion' => 'registrado'
        ], $contexto);

        $this->advertencias[] = $detalle;
    }

    private function buscarCuotaFlexible(
        int $estudianteProgramaId,
        array $rowData,
        Carbon $fechaPago,
        float $montoPago,
        int $numeroFila
    ) {
        $cuotas = $this->obtenerCuotasDelPrograma($estudianteProgramaId)
            ->sortBy('fecha_vencimiento');

        $periodo = $this->obtenerPeriodoDesdeFila($rowData, $fechaPago);
        $mesObjetivo = $periodo['mes'];
        $anioObjetivo = $periodo['anio'];
        $cuotaPorPeriodo = null;
        $cuotaPorMontoExacto = null;

        Log::info('🔍 Buscando cuota basada en periodo declarado en Excel', [
            'estudiante_programa_id' => $estudianteProgramaId,
            'mes' => $mesObjetivo,
            'anio' => $anioObjetivo,
            'monto_pago' => $montoPago,
            'fila' => $numeroFila,
            'cuotas_existentes' => $cuotas->count()
        ]);

        if ($mesObjetivo && $anioObjetivo) {
            $cuotaPorPeriodo = $cuotas->first(function ($cuota) use ($mesObjetivo, $anioObjetivo) {
                if (empty($cuota->fecha_vencimiento)) {
                    return false;
                }

                $fecha = Carbon::parse($cuota->fecha_vencimiento);
                return $fecha->month === $mesObjetivo && $fecha->year === $anioObjetivo;
            });

            if ($cuotaPorPeriodo) {
                Log::info('✅ Cuota encontrada por coincidencia de periodo', [
                    'cuota_id' => $cuotaPorPeriodo->id,
                    'fecha_vencimiento' => $cuotaPorPeriodo->fecha_vencimiento,
                    'monto_cuota' => $cuotaPorPeriodo->monto,
                    'periodo_pago' => $this->formatearPeriodo($mesObjetivo, $anioObjetivo)
                ]);

                $this->registrarDesfasePeriodo(
                    $fechaPago,
                    $mesObjetivo,
                    $anioObjetivo,
                    $estudianteProgramaId,
                    $numeroFila,
                    'coincidencia_existente',
                    $cuotaPorPeriodo->id
                );

                return $cuotaPorPeriodo;
            }
            Log::warning('⚠️ No se encontró cuota existente que coincida con el periodo declarado', [
                'estudiante_programa_id' => $estudianteProgramaId,
                'mes' => $mesObjetivo,
                'anio' => $anioObjetivo,
                'monto_pago' => $montoPago,
                'fila' => $numeroFila,
                'tipo_archivo' => $this->tipoArchivo,
            ]);
        }

        $cuotaPorMontoExacto = $cuotas->first(function ($cuota) use ($montoPago) {
            return abs($cuota->monto - $montoPago) < 0.01;
        });

        if ($cuotaPorMontoExacto) {
            Log::info('✅ Cuota encontrada por coincidencia exacta de monto', [
                'cuota_id' => $cuotaPorMontoExacto->id,
                'monto_cuota' => $cuotaPorMontoExacto->monto
            ]);
            return $cuotaPorMontoExacto;
        }

        if ($mesObjetivo && $anioObjetivo) {
            $this->registrarDesfasePeriodo(
                $fechaPago,
                $mesObjetivo,
                $anioObjetivo,
                $estudianteProgramaId,
                $numeroFila,
                'sin_coincidencia'
            );
        }

        Log::warning('⚠️ No se encontró cuota pendiente compatible. No se generará automáticamente.', [
            'estudiante_programa_id' => $estudianteProgramaId,
            'mes' => $mesObjetivo,
            'anio' => $anioObjetivo,
            'monto_pago' => $montoPago,
            'fila' => $numeroFila,
            'tipo_archivo' => $this->tipoArchivo,
        ]);

        return null;
    }

    private function actualizarCuotaYConciliar($cuota, $kardex, $numeroFila, $montoPago)
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

        $this->crearConciliacionDesdeKardex($kardex, 'actualizacion_cuota');
    }

    private function crearConciliacionDesdeKardex($kardex, string $contexto)
    {
        $bancoOriginal = $kardex->banco ?? '';
        $bancoNormalizado = $this->normalizeBank($bancoOriginal);
        $boletaNormalizada = $this->normalizeReceiptNumber($kardex->numero_boleta);
        $boletaNormalizadaFinal = $boletaNormalizada !== 'N/A' ? $boletaNormalizada : 'HIST' . $kardex->id;
        $referenciaOriginal = $kardex->numero_boleta ?: 'HIST-' . $kardex->id;
        $referenciaNormalizada = $boletaNormalizadaFinal;
        $fechaYmd = Carbon::parse($kardex->fecha_pago)->format('Y-m-d');
        $fingerprint = $this->makeFingerprint($bancoNormalizado, $referenciaNormalizada, $kardex->monto_pagado, $fechaYmd);

        Log::info("🔍 PASO 6: Creando registro de conciliación", [
            'kardex_id' => $kardex->id,
            'banco' => $bancoOriginal,
            'banco_normalizado' => $bancoNormalizado,
            'boleta' => $kardex->numero_boleta,
            'boleta_normalizada' => $boletaNormalizada,
            'boleta_normalizada_final' => $boletaNormalizadaFinal,
            'monto' => $kardex->monto_pagado,
            'contexto' => $contexto,
        ]);

        if (ReconciliationRecord::where('fingerprint', $fingerprint)->exists()) {
            Log::warning("⚠️ Conciliación duplicada detectada", [
                'fingerprint' => $fingerprint,
                'boleta' => $kardex->numero_boleta,
                'contexto' => $contexto,
            ]);
            return;
        }

        try {
            ReconciliationRecord::create([
                'bank' => $bancoOriginal !== '' ? $bancoOriginal : 'N/A',
                'bank_normalized' => $bancoNormalizado,
                'reference' => $referenciaOriginal,
                'reference_normalized' => $referenciaNormalizada,
                'amount' => $kardex->monto_pagado,
                'date' => $fechaYmd,
                'fingerprint' => $fingerprint,
                'status' => 'conciliado',
                'uploaded_by' => $this->uploaderId,
                'kardex_pago_id' => $kardex->id,
            ]);

            $this->conciliaciones++;

            Log::info("✅ PASO 6 EXITOSO: Conciliación creada", [
                'kardex_id' => $kardex->id,
                'fingerprint' => $fingerprint,
                'status' => 'conciliado',
                'contexto' => $contexto,
            ]);
        } catch (\Throwable $e) {
            Log::error("❌ PASO 6 FALLIDO: Error creando conciliación", [
                'error' => $e->getMessage(),
                'kardex_id' => $kardex->id,
                'fingerprint' => $fingerprint,
                'contexto' => $contexto,
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
        float $montoPago,
        Carbon $fechaPago,
        $row
    ) {
        if ($programas->count() === 0) {
            return null;
        }

        if ($programas->count() === 1) {
            return $programas->first();
        }

        $rowArray = $row instanceof Collection ? $row->toArray() : (array) $row;
        $planEstudios = trim((string) $this->obtenerValorFila($rowArray, ['plan_estudios', 'plan_estudio', 'plan'], ''));
        $planNormalizado = strtoupper(preg_replace('/[^A-Z0-9]/', '', $planEstudios));

        Log::info('🔍 Identificando programa entre múltiples opciones', [
            'total_programas' => $programas->count(),
            'plan_estudios' => $planEstudios,
            'plan_normalizado' => $planNormalizado,
            'monto_pago' => $montoPago,
            'fecha_pago' => $fechaPago->toDateString()
        ]);

        if ($planNormalizado !== '') {
            $porPlan = $programas->first(function ($programa) use ($planNormalizado) {
                $abreviatura = strtoupper(preg_replace('/[^A-Z0-9]/', '', $programa->programa_abreviatura ?? ''));
                $nombre = strtoupper(preg_replace('/[^A-Z0-9]/', '', $programa->nombre_programa ?? ''));

                return $abreviatura === $planNormalizado
                    || $nombre === $planNormalizado
                    || ($abreviatura !== '' && str_contains($planNormalizado, $abreviatura))
                    || ($nombre !== '' && str_contains($planNormalizado, $nombre))
                    || ($abreviatura !== '' && str_contains($abreviatura, $planNormalizado));
            });

            if ($porPlan) {
                Log::info('✅ Programa identificado por coincidencia de plan_estudios', [
                    'estudiante_programa_id' => $porPlan->estudiante_programa_id,
                    'programa' => $porPlan->nombre_programa,
                    'abreviatura' => $porPlan->programa_abreviatura
                ]);
                return $porPlan;
            }
        }

        $periodo = $this->obtenerPeriodoDesdeFila($rowArray, $fechaPago);
        if ($periodo['mes'] && $periodo['anio']) {
            $porPeriodo = $programas->first(function ($programa) use ($periodo) {
                $cuotas = $this->obtenerCuotasDelPrograma($programa->estudiante_programa_id);

                return $cuotas->first(function ($cuota) use ($periodo) {
                    if (empty($cuota->fecha_vencimiento)) {
                        return false;
                    }

                    $fecha = Carbon::parse($cuota->fecha_vencimiento);
                    return $fecha->month === $periodo['mes'] && $fecha->year === $periodo['anio'];
                });
            });

            if ($porPeriodo) {
                Log::info('✅ Programa identificado por periodo (mes/año)', [
                    'estudiante_programa_id' => $porPeriodo->estudiante_programa_id,
                    'programa' => $porPeriodo->nombre_programa,
                    'periodo_mes' => $periodo['mes'],
                    'periodo_anio' => $periodo['anio']
                ]);
                return $porPeriodo;
            }
        }

        foreach ($programas as $programa) {
            $cuotas = $this->obtenerCuotasDelPrograma($programa->estudiante_programa_id);
            if ($cuotas->isEmpty()) {
                continue;
            }

            $primera = $cuotas->min('fecha_vencimiento');
            $ultima = $cuotas->max('fecha_vencimiento');

            if ($primera && $ultima) {
                if ($fechaPago->between(
                    Carbon::parse($primera)->subDays(15),
                    Carbon::parse($ultima)->addDays(15)
                )) {
                    Log::info('✅ Programa identificado por rango de fechas de cuotas', [
                        'estudiante_programa_id' => $programa->estudiante_programa_id,
                        'programa' => $programa->nombre_programa,
                        'rango' => $primera . ' - ' . $ultima
                    ]);
                    return $programa;
                }
            }
        }

        $porMonto = $programas->first(function ($programa) use ($montoPago) {
            $cuotas = $this->obtenerCuotasDelPrograma($programa->estudiante_programa_id);
            return $cuotas->first(function ($cuota) use ($montoPago) {
                return abs($cuota->monto - $montoPago) < 0.01;
            });
        });

        if ($porMonto) {
            Log::info('✅ Programa identificado por coincidencia exacta de monto en cuotas', [
                'estudiante_programa_id' => $porMonto->estudiante_programa_id,
                'programa' => $porMonto->nombre_programa,
                'monto_pago' => $montoPago
            ]);
            return $porMonto;
        }

        Log::warning('⚠️ No se pudo identificar programa con reglas específicas, usando el primero disponible', [
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
            ->where(DB::raw("REPLACE(UPPER(carnet), ' ', '')"), '=', $carnet)
            ->first();

        // 🔥 NUEVO: Si no existe prospecto, crearlo
        if (!$prospecto && $row) {
            Log::warning("❌ Prospecto no encontrado, creando desde datos de pago", [
                'carnet' => $carnet
            ]);

            // Convert Collection to array if needed
            $rowArray = $row instanceof Collection ? $row->toArray() : $row;
            $programaCreado = $this->estudianteService->syncEstudianteFromPaymentRow($rowArray, $this->uploaderId);

            if ($programaCreado) {
                $this->estudiantesCache[$carnet] = collect([$programaCreado]);
                return collect([$programaCreado]);
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

            // Convert Collection to array if needed
            $rowArray = $row instanceof Collection ? $row->toArray() : $row;
            $programaCreado = $this->estudianteService->syncEstudianteFromPaymentRow($rowArray, $this->uploaderId);

            if ($programaCreado) {
                $this->estudiantesCache[$carnet] = collect([$programaCreado]);
                return collect([$programaCreado]);
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

    private function guardarResumenMigracion(array $reporte): void
    {
        try {
            $ruta = 'import_reports/payment_history_summary_' . now()->format('Ymd_His') . '.json';
            Storage::disk('local')->put($ruta, json_encode($reporte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            Log::info('📝 Reporte final de migración almacenado', [
                'path' => storage_path('app/' . $ruta),
                'total_duplicados_permitidos' => $reporte['total_duplicados_permitidos'],
                'total_errores_criticos' => $reporte['total_errores_criticos'],
            ]);
        } catch (\Throwable $ex) {
            Log::warning('⚠️ No se pudo generar el archivo JSON con el resumen final de migración', [
                'error' => $ex->getMessage(),
            ]);
        }
    }

    /**
     * 🆕 Determinar si un pago es de tipo mensual
     * Acepta variaciones como: MENSUAL, Mensual, mensualidad, etc.
     * Rechaza: ESPECIAL, INSCRIPCIÓN, RECARGO, etc.
     */
    private function esPagoMensual(string $tipoPago): bool
    {
        $tipoPagoNormalizado = strtoupper(trim($tipoPago));

        // Tipos que se consideran mensuales
        $tiposMensuales = ['MENSUAL', 'MENSUALIDAD', 'CUOTA', 'CUOTA MENSUAL'];

        foreach ($tiposMensuales as $tipo) {
            if (str_contains($tipoPagoNormalizado, $tipo)) {
                return true;
            }
        }

        // Tipos especiales que NO son mensuales
        $tiposEspeciales = ['ESPECIAL', 'INSCRIPCION', 'INSCRIPCIÓN', 'RECARGO', 'MORA', 'EXTRAORDINARIO'];

        foreach ($tiposEspeciales as $tipo) {
            if (str_contains($tipoPagoNormalizado, $tipo)) {
                return false;
            }
        }

        // Por defecto, si no se reconoce, asumir que es mensual
        // para mantener compatibilidad con datos antiguos
        return true;
    }

    private function obtenerValorFila($row, array $columnas, $default = null)
    {
        if ($row instanceof Collection) {
            $row = $row->toArray();
        }

        foreach ($columnas as $columna) {
            if (is_array($row) && array_key_exists($columna, $row)) {
                $valor = $row[$columna];
                if ($valor !== null && $valor !== '') {
                    return $valor;
                }
            }
        }

        return $default;
    }

    private function obtenerPeriodoDesdeFila($row, ?Carbon $fechaPago = null): array
    {
        $rowArray = $row instanceof Collection ? $row->toArray() : (array) $row;

        $mesRaw = $this->obtenerValorFila($rowArray, ['mes_pago', 'mes']);
        $anioRaw = $this->obtenerValorFila($rowArray, ['año', 'ano', 'anio', 'year']);

        $mes = $this->normalizarMes($mesRaw);
        $anio = $this->normalizarAnio($anioRaw);

        if (!$mes && $fechaPago) {
            $mes = (int) $fechaPago->format('m');
        }

        if (!$anio && $fechaPago) {
            $anio = (int) $fechaPago->format('Y');
        }

        $fecha = null;
        if ($mes && $anio) {
            try {
                $fecha = Carbon::create($anio, $mes, 1)->startOfMonth();
            } catch (\Throwable $e) {
                $fecha = $fechaPago ? $fechaPago->copy()->startOfMonth() : null;
            }
        } elseif ($fechaPago) {
            $fecha = $fechaPago->copy()->startOfMonth();
        }

        return [
            'mes' => $mes,
            'anio' => $anio,
            'fecha' => $fecha,
        ];
    }

    private function formatearPeriodo(?int $mes, ?int $anio): ?string
    {
        if (!$mes || !$anio) {
            return null;
        }

        return sprintf('%04d-%02d', $anio, $mes);
    }

    private function registrarDesfasePeriodo(
        ?Carbon $fechaPago,
        ?int $mesObjetivo,
        ?int $anioObjetivo,
        int $estudianteProgramaId,
        int $numeroFila,
        string $contexto,
        ?int $cuotaId = null
    ): void {
        if (!$fechaPago || !$mesObjetivo || !$anioObjetivo) {
            return;
        }

        if ((int) $fechaPago->format('m') === $mesObjetivo && (int) $fechaPago->format('Y') === $anioObjetivo) {
            return;
        }

        Log::info('📌 Diferencia entre fecha de pago y periodo objetivo', [
            'estudiante_programa_id' => $estudianteProgramaId,
            'fila' => $numeroFila,
            'fecha_pago_real' => $fechaPago->toDateString(),
            'periodo_objetivo' => sprintf('%04d-%02d', $anioObjetivo, $mesObjetivo),
            'contexto' => $contexto,
            'cuota_id' => $cuotaId,
        ]);
    }

    private function kardexSoportaPeriodoPago(): bool
    {
        if ($this->kardexTienePeriodoPago !== null) {
            return $this->kardexTienePeriodoPago;
        }

        try {
            $this->kardexTienePeriodoPago = Schema::hasColumn('kardex_pagos', 'periodo_pago');
        } catch (\Throwable $e) {
            Log::warning('⚠️ No se pudo verificar la columna periodo_pago en kardex_pagos', [
                'error' => $e->getMessage(),
            ]);

            $this->kardexTienePeriodoPago = false;
        }

        return $this->kardexTienePeriodoPago;
    }

    private function normalizarMes($mes): ?int
    {
        if (is_null($mes)) {
            return null;
        }

        if ($mes instanceof Carbon) {
            return (int) $mes->format('m');
        }

        if (is_numeric($mes)) {
            $numero = (int) $mes;
            if ($numero >= 1 && $numero <= 12) {
                return $numero;
            }
        }

        $mes = strtolower(trim((string) $mes));
        $mes = strtr($mes, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
        $mes = preg_replace('/[^a-z]/', '', $mes);

        $map = [
            'enero' => 1,
            'ene' => 1,
            'january' => 1,
            'jan' => 1,
            'febrero' => 2,
            'feb' => 2,
            'february' => 2,
            'marzo' => 3,
            'mar' => 3,
            'march' => 3,
            'abril' => 4,
            'abr' => 4,
            'april' => 4,
            'mayo' => 5,
            'may' => 5,
            'junio' => 6,
            'jun' => 6,
            'june' => 6,
            'julio' => 7,
            'jul' => 7,
            'july' => 7,
            'agosto' => 8,
            'ago' => 8,
            'august' => 8,
            'aug' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'sep' => 9,
            'sept' => 9,
            'september' => 9,
            'octubre' => 10,
            'oct' => 10,
            'october' => 10,
            'noviembre' => 11,
            'nov' => 11,
            'november' => 11,
            'diciembre' => 12,
            'dic' => 12,
            'december' => 12,
            'dec' => 12,
        ];

        return $map[$mes] ?? null;
    }

    private function normalizarAnio($anio): ?int
    {
        if (is_null($anio)) {
            return null;
        }

        if ($anio instanceof Carbon) {
            return (int) $anio->format('Y');
        }

        if (is_numeric($anio)) {
            $numero = (int) $anio;
            if ($numero < 100) {
                $numero += $numero >= 50 ? 1900 : 2000;
            }

            if ($numero >= 1900 && $numero <= 2100) {
                return $numero;
            }
        }

        $anio = preg_replace('/[^0-9]/', '', (string) $anio);
        if ($anio === '') {
            return null;
        }

        $numero = (int) $anio;
        if ($numero < 100) {
            $numero += $numero >= 50 ? 1900 : 2000;
        }

        return ($numero >= 1900 && $numero <= 2100) ? $numero : null;
    }

    private function normalizarClave(string $valor): string
    {
        $valor = strtolower(trim($valor));
        $valor = strtr($valor, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $valor = preg_replace('/[^a-z0-9]+/', '_', $valor);

        return trim($valor, '_');
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
