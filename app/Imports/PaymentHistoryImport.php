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
use App\Models\AdicionalEstudiante;
use App\Models\CuotaProgramaEstudiante;
use App\Models\ReconciliationRecord;
use App\Models\PrecioPrograma;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

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

    private const DEFAULT_PAYMENT_DATE = '2020-01-01';
    private const DEFAULT_BANK = 'No especificado';
    private const DEFAULT_PAYMENT_TYPE = 'Mensual';

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
            'numero_boleta',
            'monto',
        ];

        $columnasOpcionales = [
            'fecha_pago',
            'tipo_pago',
            'mes_pago',
            'ano',
            'año',
            'anio',
            'mes_inicio',
            'plan_estudios',
            'estatus',
            'asesor',
            'empresa_donde_labora',
            'telefono',
            'mail',
            'banco',
            'concepto',
            'fila_origen',
            'mensualidad_aprobada',
            'notas_pago',
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
        $columnasFaltantes = array_diff($columnasRequeridas, $columnasEncontradas);

        $resultado = [
            'valido' => empty($columnasFaltantes),
            'faltantes' => array_values($columnasFaltantes),
            'encontradas' => $columnasEncontradas,
            'opcionales_encontradas' => array_intersect($columnasOpcionales, $columnasEncontradas)
        ];

        if (!in_array('fecha_pago', $columnasEncontradas, true)) {
            $this->advertencias[] = [
                'tipo' => 'COLUMNA_FALTANTE_NO_CRITICA',
                'advertencia' => 'Columna fecha_pago ausente, se usará fecha por defecto en filas sin valor explícito.',
            ];

            Log::warning('⚠️ Columna fecha_pago ausente, se utilizará la fecha por defecto para los pagos.');
        }

        if (!in_array('tipo_pago', $columnasEncontradas, true)) {
            Log::info('ℹ️ Columna tipo_pago ausente, se asumirá "Mensual" por defecto.');
        }

        if (!in_array('mes_pago', $columnasEncontradas, true)) {
            Log::info('ℹ️ Columna mes_pago ausente, se utilizará la fecha del pago para determinar mes/año.');
        }

        return $resultado;
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
        $carnet = $this->normalizarCarnet($row['carnet']);
        $nombreEstudiante = trim($row['nombre_estudiante'] ?? '');
        $numeroBoletaOriginal = trim((string)($row['numero_boleta'] ?? ''));
        $numeroBoletaNormalizada = $this->normalizarBoleta($numeroBoletaOriginal);
        $monto = $this->normalizarMonto($row['monto'] ?? 0);

        $fechaPagoString = $this->parseDate($row['fecha_pago'] ?? null, self::DEFAULT_PAYMENT_DATE);
        if (empty($row['fecha_pago'])) {
            Log::warning("⚠️ Fila {$numeroFila} sin fecha_pago, asignando fecha por defecto.");
        }
        $fechaPago = Carbon::parse($fechaPagoString);

        $bancoRaw = trim((string)($row['banco'] ?? ''));
        $banco = $bancoRaw !== '' ? $bancoRaw : self::DEFAULT_BANK;

        $concepto = trim((string)($row['concepto'] ?? ''));
        $tipoPagoOriginal = trim((string)($row['tipo_pago'] ?? ''));
        if ($tipoPagoOriginal === '') {
            $tipoPagoOriginal = self::DEFAULT_PAYMENT_TYPE;
        }
        $tipoPagoNormalizado = strtoupper($tipoPagoOriginal);

        $notasPago = $this->normalizarCampoLibre($row['notas_pago'] ?? null);
        $nomenclatura = $this->normalizarCampoLibre($row['nomenclatura'] ?? null);

        $mesPago = $this->parsearMes($row['mes_pago'] ?? null);
        $anioPago = $this->parsearAnio($row['año'] ?? ($row['ano'] ?? null), $fechaPago);
        $mesInicio = trim((string)($row['mes_inicio'] ?? ''));
        $mensualidadAprobada = $this->normalizarMonto($row['mensualidad_aprobada'] ?? 0);

        Log::info("📄 Procesando fila {$numeroFila}", [
            'carnet' => $carnet,
            'nombre' => $nombreEstudiante,
            'boleta_original' => $numeroBoletaOriginal,
            'boleta_normalizada' => $numeroBoletaNormalizada,
            'monto' => $monto,
            'fecha_pago' => $fechaPago->toDateString(),
            'mensualidad_aprobada' => $mensualidadAprobada,
            'tipo_pago' => $tipoPagoOriginal,
            'mes_pago' => $row['mes_pago'] ?? null,
            'anio_pago' => $row['año'] ?? ($row['ano'] ?? null)
        ]);

        $this->guardarInformacionAdicionalEstudiante($carnet, $notasPago, $nomenclatura);

        $validacion = $this->validarDatosPago($numeroBoletaOriginal, $monto, $fechaPago, $numeroFila);
        if (!$validacion['valido']) {
            $this->advertencias[] = $validacion['advertencia'];
            return;
        }

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
            ];

            Log::error("❌ No se pudo identificar programa", [
                'carnet' => $carnet,
                'mensualidad' => $mensualidadAprobada,
                'monto' => $monto,
                'fecha_pago' => $fechaPago->toDateString()
            ]);
            return;
        }

        Log::info("✅ Programa asignado", [
            'fila' => $numeroFila,
            'programa_id' => $programaAsignado->programa_id,
            'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
            'programa' => $programaAsignado->nombre_programa ?? 'N/A'
        ]);

        $esPagoMensual = $this->esPagoMensual($tipoPagoNormalizado);

        DB::transaction(function () use (
            $programaAsignado,
            $carnet,
            $nombreEstudiante,
            $numeroBoletaOriginal,
            $numeroBoletaNormalizada,
            $monto,
            $fechaPago,
            $banco,
            $concepto,
            $numeroFila,
            $tipoPagoOriginal,
            $tipoPagoNormalizado,
            $mesPago,
            $anioPago,
            $esPagoMensual
        ) {
            $cuota = null;
            $cuotaCreada = false;

            if ($esPagoMensual) {
                [$cuota, $cuotaCreada] = $this->obtenerOCrearCuotaMensual(
                    $programaAsignado,
                    $monto,
                    $mesPago,
                    $anioPago,
                    $fechaPago,
                    $numeroFila
                );
            }


            Log::info("🔄 Iniciando transacción para fila {$numeroFila}");

            $referenciaParaGuardar = $numeroBoletaOriginal !== ''
                ? $numeroBoletaOriginal
                : sprintf('HIST-%05d', $numeroFila);

            $referenciaParaVerificacion = $referenciaParaGuardar;
            $kardexExistente = KardexPago::where('numero_boleta', $referenciaParaVerificacion)
                ->where('estudiante_programa_id', $programaAsignado->estudiante_programa_id)
                ->first();

            if ($kardexExistente) {
                Log::info("⚠️ Kardex duplicado detectado (por boleta+estudiante)", [
                    'kardex_id' => $kardexExistente->id,
                    'boleta' => $referenciaParaVerificacion,
                    'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
                ]);

                $this->advertencias[] = [
                    'tipo' => 'DUPLICADO',
                    'fila' => $numeroFila,
                    'advertencia' => 'Pago ya registrado anteriormente',
                    'kardex_id' => $kardexExistente->id,
                    'boleta' => $referenciaParaVerificacion,
                    'accion' => 'omitido',
                ];
                return;
            }

            $bancoNormalizado = $this->normalizeBank($banco);
            $boletaNormalizadaVerificacion = $this->normalizeReceiptNumber($referenciaParaVerificacion);
            $fechaYmd = $fechaPago->toDateString();
            $fingerprintKardex = hash('sha256', implode('|', [
                $bancoNormalizado,
                $boletaNormalizadaVerificacion,
                $programaAsignado->estudiante_programa_id,
                $fechaYmd,
            ]));

            $kardexPorFingerprint = KardexPago::where('boleta_fingerprint', $fingerprintKardex)->first();

            if ($kardexPorFingerprint) {
                Log::info("⚠️ Kardex duplicado detectado (por fingerprint)", [
                    'kardex_id' => $kardexPorFingerprint->id,
                    'fingerprint' => $fingerprintKardex,
                    'boleta' => $referenciaParaVerificacion,
                    'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
                ]);

                $this->advertencias[] = [
                    'tipo' => 'DUPLICADO',
                    'fila' => $numeroFila,
                    'advertencia' => 'Pago ya registrado anteriormente (mismo fingerprint)',
                    'kardex_id' => $kardexPorFingerprint->id,
                    'boleta' => $referenciaParaVerificacion,
                    'accion' => 'omitido',
                ];
                return;
            }

            if ($numeroBoletaOriginal === '') {
                Log::warning("⚠️ Fila {$numeroFila} sin numero_boleta, generando referencia automática", [
                    'referencia_generada' => $referenciaParaGuardar
                ]);
            }

            $kardex = KardexPago::create([
                'estudiante_programa_id' => $programaAsignado->estudiante_programa_id,
                'cuota_id' => $cuota?->id,
                'numero_boleta' => $referenciaParaGuardar,
                'monto_pagado' => $monto,
                'fecha_pago' => $fechaPago,
                'fecha_recibo' => $fechaPago,
                'banco' => $banco,
                'metodo_pago' => $tipoPagoOriginal,
                'estado_pago' => 'pagado',
                'observaciones' => $concepto !== '' ? $concepto : null,
                'uploaded_by' => $this->uploaderId,
                'created_by' => $this->uploaderId,
            ]);

            $this->kardexCreados++;
            $this->totalAmount += $monto;

            $contextoConciliacion = [
                'banco' => $banco,
                'monto' => $monto,
                'carnet' => $carnet,
                'programa_id' => $programaAsignado->programa_id,
                'prospecto_id' => $programaAsignado->prospecto_id ?? null,
                'numero_boleta_original' => $numeroBoletaOriginal,
                'numero_boleta_normalizada' => $numeroBoletaNormalizada,
                'fecha_pago' => $fechaPago,
                'concepto' => $concepto,
                'tipo_pago' => $tipoPagoNormalizado,
                'referencia_guardada' => $referenciaParaGuardar,
                'cuota_creada' => $cuotaCreada,
            ];

            $this->actualizarCuotaYConciliar($cuota, $kardex, $numeroFila, $contextoConciliacion);

            $this->procesados++;

            $this->detalles[] = [
                'accion' => 'pago_registrado',
                'fila' => $numeroFila,
                'carnet' => $carnet,
                'nombre' => $nombreEstudiante,
                'kardex_id' => $kardex->id,
                'cuota_id' => $cuota?->id,
                'programa' => $programaAsignado->nombre_programa ?? 'N/A',
                'monto' => $monto,
                'fecha_pago' => $fechaPago->toDateString(),
                'numero_boleta' => $referenciaParaGuardar,
                'concepto' => $concepto,
                'tipo_pago' => $tipoPagoOriginal,
            ];
        });
    }


    private function obtenerOCrearCuotaMensual($programaAsignado, float $montoPago, ?int $mes, ?int $anio, Carbon $fechaPago, int $numeroFila): array
    {
        if ($montoPago <= 0) {
            Log::warning("⚠️ Monto inválido para generar cuota", [
                'fila' => $numeroFila,
                'monto' => $montoPago,
            ]);

            return [null, false];
        }

        $estudianteProgramaId = $programaAsignado->estudiante_programa_id;
        $anio = $anio ?? $fechaPago->year;
        $mes = $mes ?? $fechaPago->month;
        $fechaVencimiento = $this->construirFechaVencimiento($mes, $anio, $fechaPago);

        Log::info("🔍 Buscando cuota por mes/año", [
            'estudiante_programa_id' => $estudianteProgramaId,
            'mes' => $mes,
            'anio' => $anio,
            'fecha_vencimiento' => $fechaVencimiento->toDateString(),
            'monto_pago' => $montoPago,
        ]);

        $cuotas = CuotaProgramaEstudiante::where('estudiante_programa_id', $estudianteProgramaId)
            ->whereYear('fecha_vencimiento', $fechaVencimiento->year)
            ->whereMonth('fecha_vencimiento', $fechaVencimiento->month)
            ->orderBy('id')
            ->get();

        $cuotaSeleccionada = $cuotas->first(function (CuotaProgramaEstudiante $cuota) use ($montoPago) {
            $base = (float)$cuota->monto;
            if ($base <= 0) {
                return true;
            }

            return abs($montoPago - $base) <= ($base * 0.10);
        });

        if (!$cuotaSeleccionada && $cuotas->isNotEmpty()) {
            $cuotaSeleccionada = $cuotas->first();
        }

        if ($cuotaSeleccionada) {
            Log::info("✅ Cuota existente reutilizada", [
                'cuota_id' => $cuotaSeleccionada->id,
                'monto_anterior' => $cuotaSeleccionada->monto,
                'monto_pago' => $montoPago,
            ]);

            $cambios = [];
            if ((float)$cuotaSeleccionada->monto !== (float)$montoPago) {
                $cambios['monto'] = $montoPago;
            }

            if ($cuotaSeleccionada->fecha_vencimiento->toDateString() !== $fechaVencimiento->toDateString()) {
                $cambios['fecha_vencimiento'] = $fechaVencimiento->toDateString();
            }

            $cambios['estado'] = 'pagado';
            $cambios['paid_at'] = $fechaPago;

            if (!empty($cambios)) {
                $cuotaSeleccionada->fill($cambios);
                $cuotaSeleccionada->save();
            }

            unset($this->cuotasPorEstudianteCache[$estudianteProgramaId]);

            return [$cuotaSeleccionada->fresh(), false];
        }

        $numeroCuota = CuotaProgramaEstudiante::where('estudiante_programa_id', $estudianteProgramaId)
            ->max('numero_cuota');
        $numeroCuota = $numeroCuota ? $numeroCuota + 1 : 1;

        $nuevaCuota = CuotaProgramaEstudiante::create([
            'estudiante_programa_id' => $estudianteProgramaId,
            'numero_cuota' => $numeroCuota,
            'fecha_vencimiento' => $fechaVencimiento->toDateString(),
            'monto' => $montoPago,
            'estado' => 'pagado',
            'paid_at' => $fechaPago,
        ]);

        unset($this->cuotasPorEstudianteCache[$estudianteProgramaId]);

        Log::info("🆕 Cuota creada automáticamente", [
            'cuota_id' => $nuevaCuota->id,
            'estudiante_programa_id' => $estudianteProgramaId,
            'mes' => $mes,
            'anio' => $anio,
            'monto' => $montoPago,
        ]);

        return [$nuevaCuota, true];
    }

    private function construirFechaVencimiento(?int $mes, ?int $anio, Carbon $fallback): Carbon
    {
        $anio = $anio ?? $fallback->year;
        $mes = $mes ?? $fallback->month;

        try {
            return Carbon::createFromDate($anio, $mes, 1);
        } catch (\Throwable $e) {
            Log::warning("⚠️ Mes o año inválido en datos de pago", [
                'mes' => $mes,
                'anio' => $anio,
                'error' => $e->getMessage(),
            ]);

            return $fallback->copy();
        }
    }

    private function parsearMes($valor): ?int
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string)$valor);
        if ($valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            $mes = (int)$valor;
            return $mes >= 1 && $mes <= 12 ? $mes : null;
        }

        $normalizado = mb_strtolower($valor, 'UTF-8');
        $normalizado = strtr($normalizado, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u'
        ]);
        $normalizado = preg_replace('/[^a-z]/', '', $normalizado);

        $mapa = [
            'enero' => 1,
            'ene' => 1,
            'febrero' => 2,
            'feb' => 2,
            'marzo' => 3,
            'mar' => 3,
            'abril' => 4,
            'abr' => 4,
            'mayo' => 5,
            'may' => 5,
            'junio' => 6,
            'jun' => 6,
            'julio' => 7,
            'jul' => 7,
            'agosto' => 8,
            'ago' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'sep' => 9,
            'octubre' => 10,
            'oct' => 10,
            'noviembre' => 11,
            'nov' => 11,
            'diciembre' => 12,
            'dic' => 12,
        ];

        return $mapa[$normalizado] ?? null;
    }

    private function parsearAnio($valor, Carbon $fechaReferencia): int
    {
        if (is_numeric($valor)) {
            $anio = (int)$valor;
            return $anio >= 1900 && $anio <= 2100 ? $anio : $fechaReferencia->year;
        }

        if (is_string($valor)) {
            if (preg_match('/(19|20)\\d{2}/', $valor, $coincidencias)) {
                return (int)$coincidencias[0];
            }
        }

        return $fechaReferencia->year;
    }
    private function actualizarCuotaYConciliar($cuota, $kardex, $numeroFila, array $contexto)
    {
        $montoPago = $contexto['monto'] ?? $kardex->monto_pagado;
        $banco = $contexto['banco'] ?? $kardex->banco;
        $carnet = $contexto['carnet'] ?? '';
        $programaId = $contexto['programa_id'] ?? null;
        $prospectoId = $contexto['prospecto_id'] ?? null;
        $fechaPago = $contexto['fecha_pago'] instanceof Carbon
            ? $contexto['fecha_pago']
            : Carbon::parse($kardex->fecha_pago);
        $referenciaOriginal = $contexto['numero_boleta_original'] ?? '';
        $referenciaGuardada = $contexto['referencia_guardada'] ?? $kardex->numero_boleta;
        $referenciaParaFingerprint = $referenciaOriginal !== '' ? $referenciaOriginal : $referenciaGuardada;

        if ($cuota) {
            $diferencia = (float)$cuota->monto - (float)$montoPago;

            Log::info("🔄 Actualizando cuota asociada", [
                'fila' => $numeroFila,
                'cuota_id' => $cuota->id,
                'monto_actual' => $cuota->monto,
                'monto_pago' => $montoPago,
                'diferencia' => round($diferencia, 2),
                'creada_durante_import' => $contexto['cuota_creada'] ?? false,
            ]);

            $cambios = [];
            if ((float)$cuota->monto !== (float)$montoPago) {
                $cambios['monto'] = $montoPago;
            }

            if (($cuota->estado ?? null) !== 'pagado') {
                $cambios['estado'] = 'pagado';
            }

            $cambios['paid_at'] = $fechaPago;

            if (!empty($cambios)) {
                $cuota->fill($cambios);
                $cuota->save();
            }

            $this->cuotasActualizadas++;
        } else {
            Log::info("⏭️ Pago sin cuota asociada", [
                'fila' => $numeroFila,
                'kardex_id' => $kardex->id,
                'motivo' => 'Pago no clasificado como mensual',
            ]);
        }

        $bancoNormalizado = $this->normalizeBank($banco);
        $referenciaNormalizada = $this->normalizeReceiptNumber($referenciaParaFingerprint ?: $referenciaGuardada);
        $fechaYmd = $fechaPago->toDateString();
        $fingerprint = $this->makeFingerprint($carnet, $programaId, $referenciaParaFingerprint, $montoPago, $fechaYmd);

        $payloadBase = [
            'prospecto_id' => $prospectoId,
            'bank' => $banco,
            'bank_normalized' => $bancoNormalizado,
            'reference' => $referenciaGuardada,
            'reference_normalized' => $referenciaNormalizada,
            'amount' => $montoPago,
            'date' => $fechaYmd,
            'status' => 'conciliado',
            'fingerprint' => $fingerprint,
            'kardex_pago_id' => $kardex->id,
        ];

        $record = ReconciliationRecord::where('fingerprint', $fingerprint)->first();

        if ($record) {
            $record->fill($payloadBase);
            $record->save();

            Log::info("🔁 Conciliación actualizada", [
                'fingerprint' => $fingerprint,
                'kardex_id' => $kardex->id,
            ]);
        } else {
            $payloadBase['uploaded_by'] = $this->uploaderId;

            ReconciliationRecord::create($payloadBase);
            $this->conciliaciones++;

            Log::info("✅ Conciliación creada", [
                'fingerprint' => $fingerprint,
                'kardex_id' => $kardex->id,
            ]);
        }
    }

    private function guardarInformacionAdicionalEstudiante(string $carnet, ?string $notasPago, ?string $nomenclatura): void
    {
        $datos = [];

        if ($notasPago !== null) {
            $datos['notas_pago'] = $notasPago;
        }

        if ($nomenclatura !== null) {
            $datos['nomenclatura'] = $nomenclatura;
        }

        if (empty($datos)) {
            return;
        }

        try {
            $registro = AdicionalEstudiante::updateOrCreate(
                ['carnet' => $carnet],
                $datos
            );

            Log::info('📝 Información adicional de estudiante guardada', [
                'carnet' => $carnet,
                'campos_actualizados' => array_keys($datos),
                'registro_id' => $registro->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ Error guardando información adicional de estudiante', [
                'carnet' => $carnet,
                'datos' => $datos,
                'error' => $e->getMessage(),
            ]);

            $this->advertencias[] = [
                'tipo' => 'INFO_ADICIONAL_NO_GUARDADA',
                'carnet' => $carnet,
                'advertencia' => 'No se pudo guardar la información adicional del estudiante',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function normalizarCampoLibre($valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valorNormalizado = trim((string)$valor);

        return $valorNormalizado === '' ? null : $valorNormalizado;
    }

    private function validarDatosPago($boleta, $monto, $fechaPago, $numeroFila): array
    {
        $errores = [];

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

        if (empty($boleta) || trim($boleta) === '') {
            Log::warning("⚠️ Fila {$numeroFila} sin numero_boleta explícito. Se generará referencia automática.");
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
     * 🔥 ACTUALIZADO: Ahora cuenta pagos "Mensual" del Excel y crea cuotas faltantes
     */
    private function generarCuotasSiFaltan(int $estudianteProgramaId, ?array $row = null)
    {
        try {
            // Obtener datos del estudiante_programa
            $estudiantePrograma = DB::table('estudiante_programa')
                ->where('id', $estudianteProgramaId)
                ->first();

            if (!$estudiantePrograma) {
                Log::warning("⚠️ No se encontró estudiante_programa", [
                    'estudiante_programa_id' => $estudianteProgramaId
                ]);
                return false;
            }

            // Usar duracion_meses del estudiante_programa
            $numCuotasEsperadas = $estudiantePrograma->duracion_meses ?? 0;
            $cuotaMensual = $estudiantePrograma->cuota_mensual ?? 0;
            $fechaInicio = $estudiantePrograma->fecha_inicio ?? now()->toDateString();

            // Si no hay datos suficientes en estudiante_programa, intentar con precio_programa
            if ($numCuotasEsperadas <= 0 || $cuotaMensual <= 0) {
                $precioPrograma = $this->obtenerPrecioPrograma($estudianteProgramaId);
                if ($precioPrograma) {
                    $numCuotasEsperadas = $numCuotasEsperadas > 0 ? $numCuotasEsperadas : ($precioPrograma->meses ?? 12);
                    $cuotaMensual = $cuotaMensual > 0 ? $cuotaMensual : ($precioPrograma->cuota_mensual ?? 0);
                }
            }

            // Validar que tengamos los datos mínimos
            if ($numCuotasEsperadas <= 0 || $cuotaMensual <= 0) {
                Log::warning("⚠️ No se pueden generar cuotas: datos insuficientes", [
                    'estudiante_programa_id' => $estudianteProgramaId,
                    'num_cuotas_esperadas' => $numCuotasEsperadas,
                    'cuota_mensual' => $cuotaMensual
                ]);
                return false;
            }

            // Contar cuotas existentes
            $cuotasExistentes = DB::table('cuotas_programa_estudiante')
                ->where('estudiante_programa_id', $estudianteProgramaId)
                ->count();

            Log::info("🔧 Verificando cuotas para generar", [
                'estudiante_programa_id' => $estudianteProgramaId,
                'cuotas_esperadas' => $numCuotasEsperadas,
                'cuotas_existentes' => $cuotasExistentes,
                'cuota_mensual' => $cuotaMensual,
                'fecha_inicio' => $fechaInicio
            ]);

            // Si ya tiene todas las cuotas esperadas, no hacer nada
            if ($cuotasExistentes >= $numCuotasEsperadas) {
                Log::info("✅ Ya existen suficientes cuotas", [
                    'estudiante_programa_id' => $estudianteProgramaId,
                    'cuotas_existentes' => $cuotasExistentes,
                    'cuotas_esperadas' => $numCuotasEsperadas
                ]);
                return false;
            }

            // Calcular cuántas cuotas faltan por crear
            $cuotasFaltantes = $numCuotasEsperadas - $cuotasExistentes;

            Log::info("🔧 Generando cuotas faltantes", [
                'estudiante_programa_id' => $estudianteProgramaId,
                'cuotas_faltantes' => $cuotasFaltantes,
                'num_cuotas_esperadas' => $numCuotasEsperadas,
                'cuota_mensual' => $cuotaMensual,
                'fecha_inicio' => $fechaInicio
            ]);

            // Generar las cuotas faltantes
            $cuotas = [];
            for ($i = $cuotasExistentes + 1; $i <= $numCuotasEsperadas; $i++) {
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
                'cantidad_cuotas_creadas' => count($cuotas),
                'total_cuotas_ahora' => $numCuotasEsperadas
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

    private function normalizeBank($bank)
    {
        if ($bank === null) {
            return strtoupper(self::DEFAULT_BANK);
        }

        $bank = trim((string)$bank);
        if ($bank === '' || strcasecmp($bank, 'N/A') === 0 || strcasecmp($bank, self::DEFAULT_BANK) === 0) {
            return strtoupper(self::DEFAULT_BANK);
        }

        $bankUpper = strtoupper($bank);

        $bankMappings = [
            'BANCO INDUSTRIAL' => 'BI',
            'INDUSTRIAL' => 'BI',
            'BI' => 'BI',
            'BAC' => 'BAC',
            'BANTRAB' => 'BANTRAB',
            'PROMERICA' => 'PROMERICA',
            'GYT' => 'GYT',
            'G&T' => 'GYT',
            'G Y T' => 'GYT',
            'VISA' => 'VISA',
            'MASTERCARD' => 'MASTERCARD',
        ];

        foreach ($bankMappings as $clave => $normalizado) {
            if (str_contains($bankUpper, $clave)) {
                return $normalizado;
            }
        }

        return $bankUpper;
    }

    private function normalizeReceiptNumber($receiptNumber)
    {
        if (empty($receiptNumber)) {
            return 'N/A';
        }

        return strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $receiptNumber));
    }

    private function makeFingerprint($carnet, $programaId, $reference, $amount, $date)
    {
        $componentes = [
            strtoupper(trim((string)$carnet)),
            (string)($programaId ?? '0'),
            trim((string)$reference) !== '' ? trim((string)$reference) : 'SIN_REFERENCIA',
            number_format((float)$amount, 2, '.', ''),
            $date,
        ];

        return hash('sha256', implode('|', $componentes));
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

    protected function parseDate($value, ?string $default = self::DEFAULT_PAYMENT_DATE): string
    {
        if ($value === null || $value === '') {
            return $default ?? Carbon::now()->toDateString();
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float)$value))->toDateString();
            }

            $clean = preg_replace('/[^0-9\/-]/', '', trim((string)$value));
            $clean = preg_replace('/\/+/', '/', $clean);

            foreach (['d/m/Y', 'Y-m-d', 'm/d/Y'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $clean)->toDateString();
                } catch (\Exception $ignored) {
                }
            }

            return Carbon::parse($clean)->toDateString();
        } catch (\Throwable $e) {
            $fallback = $default ?? Carbon::now()->toDateString();
            Log::warning("⚠️ Fecha inválida: {$value}. Usando valor por defecto: {$fallback}");
            return $fallback;
        }
    }

    private function normalizarFecha($fecha)
    {
        $fechaNormalizada = $this->parseDate($fecha, self::DEFAULT_PAYMENT_DATE);
        return Carbon::parse($fechaNormalizada);
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
