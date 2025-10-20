<?php

namespace App\Imports;

// ✅ Configuración de memoria y tiempo para imports grandes
ini_set('memory_limit', '2048M');
ini_set('max_execution_time', '1500');

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\KardexPago;
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
    public int $pagosOmitidos = 0; // Duplicados
    public float $totalAmount = 0;
    public array $errores = [];
    public array $advertencias = [];
    public array $detalles = [];

    private array $estudiantesCache = [];

    public function __construct(int $uploaderId, string $tipoArchivo = 'cardex_directo')
    {
        $this->uploaderId = $uploaderId;
        $this->tipoArchivo = $tipoArchivo;

        Log::info('📦 PaymentHistoryImport Constructor - Strict Import Mode', [
            'uploaderId' => $uploaderId,
            'tipoArchivo' => $tipoArchivo,
            'mode' => 'STRICT_IMPORT',
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    public function collection(Collection $rows)
    {
        $this->totalRows = $rows->count();

        Log::info('=== 🚀 INICIANDO PROCESAMIENTO (STRICT MODE) ===', [
            'total_rows' => $this->totalRows,
            'mode' => 'STRICT_IMPORT - No auto-generation, No quota matching',
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
                    'line' => $ex->getLine()
                ]);

                $this->errores[] = [
                    'tipo' => 'ERROR_PROCESAMIENTO_ESTUDIANTE',
                    'carnet' => $carnet,
                    'error' => $ex->getMessage(),
                    'cantidad_pagos_afectados' => $pagosEstudiante->count()
                ];
            }
        }

        $this->logResumenFinal();
    }

    private function validarColumnasExcel($primeraFila): array
    {
        $columnasRequeridas = [
            'carnet',
            'nombre_estudiante',
            'numero_boleta',
            'monto',
            'fecha_pago'
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
            'encontradas' => $columnasEncontradas
        ];
    }

    /**
     * Procesar pagos de un estudiante con llenado de fechas faltantes
     */
    private function procesarPagosDeEstudiante($carnet, Collection $pagos)
    {
        $carnetNormalizado = $this->normalizarCarnet($carnet);

        Log::info("=== 👤 PROCESANDO ESTUDIANTE {$carnetNormalizado} ===", [
            'cantidad_pagos' => $pagos->count()
        ]);

        // Buscar estudiante_programa_id
        $estudianteProgramaId = $this->obtenerEstudianteProgramaId($carnetNormalizado);

        if (!$estudianteProgramaId) {
            $this->errores[] = [
                'tipo' => 'ESTUDIANTE_NO_ENCONTRADO',
                'carnet' => $carnetNormalizado,
                'error' => 'No se encontró estudiante_programa para este carnet',
                'cantidad_pagos_afectados' => $pagos->count(),
                'solucion' => 'Verifica que el carnet exista en prospectos y estudiante_programa'
            ];
            Log::warning("⚠️ Estudiante no encontrado: {$carnetNormalizado}");
            return;
        }

        Log::info("✅ Estudiante encontrado", [
            'carnet' => $carnetNormalizado,
            'estudiante_programa_id' => $estudianteProgramaId
        ]);

        // Procesar fechas: rellenar fechas vacías con la última fecha válida
        $ultimaFechaValida = null;
        $pagosConFechas = [];
        
        foreach ($pagos as $i => $pago) {
            $pagoArray = $pago instanceof Collection ? $pago->toArray() : $pago;
            $fechaPago = $this->normalizarFecha($pagoArray['fecha_pago'] ?? null);
            
            if (!$fechaPago) {
                if ($ultimaFechaValida) {
                    $fechaPago = $ultimaFechaValida;
                    $this->advertencias[] = [
                        'tipo' => 'FECHA_COMPLETADA',
                        'fila' => $pagoArray['fila_origen'] ?? ($i + 2),
                        'advertencia' => 'Fecha vacía reemplazada por última fecha válida anterior',
                        'carnet' => $carnetNormalizado,
                        'fecha_usada' => $ultimaFechaValida->toDateString()
                    ];
                    Log::warning("⚠️ Fecha vacía reemplazada", [
                        'fila' => $pagoArray['fila_origen'] ?? ($i + 2),
                        'fecha_usada' => $ultimaFechaValida->toDateString()
                    ]);
                } else {
                    // No hay fecha previa, usar fecha actual
                    $fechaPago = Carbon::now();
                    $this->advertencias[] = [
                        'tipo' => 'FECHA_COMPLETADA',
                        'fila' => $pagoArray['fila_origen'] ?? ($i + 2),
                        'advertencia' => 'Fecha vacía reemplazada por fecha actual (no había fecha previa)',
                        'carnet' => $carnetNormalizado,
                        'fecha_usada' => $fechaPago->toDateString()
                    ];
                    Log::warning("⚠️ Fecha vacía sin fecha previa, usando fecha actual", [
                        'fila' => $pagoArray['fila_origen'] ?? ($i + 2),
                        'fecha_usada' => $fechaPago->toDateString()
                    ]);
                }
            } else {
                // Actualizar última fecha válida
                $ultimaFechaValida = $fechaPago;
            }
            
            // Guardar pago con fecha normalizada
            $pagoArray['fecha_pago_normalizada'] = $fechaPago;
            $pagosConFechas[] = $pagoArray;
        }

        // Procesar cada pago
        foreach ($pagosConFechas as $i => $pago) {
            $numeroFila = $pago['fila_origen'] ?? ($i + 2);

            try {
                $this->procesarPagoIndividual($pago, $estudianteProgramaId, $numeroFila, $carnetNormalizado);
            } catch (\Throwable $ex) {
                $this->errores[] = [
                    'tipo' => 'ERROR_INSERCION',
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

    private function procesarPagoIndividual($row, int $estudianteProgramaId, $numeroFila, $carnet)
    {
        // ✅ Extraer y normalizar datos
        $nombreEstudiante = trim($row['nombre_estudiante'] ?? '');
        $boleta = $this->normalizarBoleta($row['numero_boleta'] ?? '');
        $monto = $this->normalizarMonto($row['monto'] ?? 0);
        $fechaPago = $row['fecha_pago_normalizada']; // Ya viene normalizada del paso anterior
        
        // Campos opcionales con valores por defecto
        $bancoRaw = trim((string)($row['banco'] ?? ''));
        $banco = empty($bancoRaw) ? 'NO ESPECIFICADO' : $bancoRaw;
        
        $conceptoRaw = trim((string)($row['concepto'] ?? ''));
        $concepto = empty($conceptoRaw) ? 'PAGO' : $conceptoRaw;
        
        $tipoPagoRaw = trim((string)($row['tipo_pago'] ?? ''));
        $tipoPago = empty($tipoPagoRaw) ? 'MENSUAL' : strtoupper($tipoPagoRaw);
        
        $mesPagoRaw = trim((string)($row['mes_pago'] ?? ''));
        $mesPago = empty($mesPagoRaw) ? 'SIN_MES' : $mesPagoRaw;
        
        $anoRaw = trim((string)($row['ano'] ?? $row['año'] ?? ''));
        $ano = empty($anoRaw) ? Carbon::now()->year : $anoRaw;

        // Track if we used default values
        $camposFaltantes = [];
        if (empty($bancoRaw)) $camposFaltantes[] = 'banco';
        if (empty($conceptoRaw)) $camposFaltantes[] = 'concepto';
        if (empty($tipoPagoRaw)) $camposFaltantes[] = 'tipo_pago';
        if (empty($mesPagoRaw)) $camposFaltantes[] = 'mes_pago';
        if (empty($anoRaw)) $camposFaltantes[] = 'año';

        if (!empty($camposFaltantes)) {
            $this->advertencias[] = [
                'tipo' => 'CAMPOS_OPCIONALES_FALTANTES',
                'fila' => $numeroFila,
                'carnet' => $carnet,
                'campos_faltantes' => $camposFaltantes,
                'advertencia' => 'Campos opcionales faltantes fueron reemplazados por valores por defecto'
            ];
        }

        Log::info("📄 Procesando fila {$numeroFila}", [
            'carnet' => $carnet,
            'nombre' => $nombreEstudiante,
            'boleta' => $boleta,
            'monto' => $monto,
            'fecha_pago' => $fechaPago->toDateString(),
            'banco' => $banco,
            'tipo_pago' => $tipoPago
        ]);

        // ✅ Validaciones básicas de campos requeridos
        $erroresValidacion = [];
        
        if (empty($boleta) || trim($boleta) === '') {
            $erroresValidacion[] = 'Boleta vacía o inválida';
        }
        
        if (!is_numeric($monto) || $monto <= 0) {
            $erroresValidacion[] = "Monto inválido: {$monto}";
        }
        
        if (empty($fechaPago) || !($fechaPago instanceof Carbon)) {
            $erroresValidacion[] = 'Fecha de pago vacía o inválida después del ajuste';
        }

        if (!empty($erroresValidacion)) {
            $this->errores[] = [
                'tipo' => 'DATOS_INCOMPLETOS',
                'fila' => $numeroFila,
                'carnet' => $carnet,
                'errores' => $erroresValidacion,
                'datos' => [
                    'boleta' => $boleta,
                    'monto' => $monto,
                    'fecha_pago' => $fechaPago instanceof Carbon ? $fechaPago->toDateString() : 'INVÁLIDA'
                ]
            ];
            return;
        }

        // ✅ Verificar duplicado: numero_boleta + carnet + fecha_pago
        $fechaYmd = $fechaPago->format('Y-m-d');
        
        $duplicado = KardexPago::where('estudiante_programa_id', $estudianteProgramaId)
            ->where('numero_boleta', $boleta)
            ->whereDate('fecha_pago', $fechaYmd)
            ->first();

        if ($duplicado) {
            Log::info("⚠️ Pago duplicado detectado", [
                'kardex_id' => $duplicado->id,
                'boleta' => $boleta,
                'estudiante_programa_id' => $estudianteProgramaId,
                'fecha_pago' => $fechaYmd
            ]);

            $this->errores[] = [
                'tipo' => 'PAGO_DUPLICADO',
                'fila' => $numeroFila,
                'carnet' => $carnet,
                'error' => "Pago ya registrado anteriormente",
                'kardex_id' => $duplicado->id,
                'boleta' => $boleta,
                'fecha_pago' => $fechaYmd
            ];
            
            $this->pagosOmitidos++;
            return;
        }

        // ✅ Insertar directamente en kardex_pagos
        try {
            DB::transaction(function () use (
                $estudianteProgramaId,
                $boleta,
                $monto,
                $fechaPago,
                $banco,
                $concepto,
                $tipoPago,
                $mesPago,
                $ano,
                $nombreEstudiante,
                $numeroFila,
                $carnet
            ) {
                $observaciones = sprintf(
                    "%s | Estudiante: %s | Mes: %s | Tipo: %s | Año: %s | Importación fila %d",
                    $concepto,
                    $nombreEstudiante,
                    $mesPago,
                    $tipoPago,
                    $ano,
                    $numeroFila
                );

                $kardex = KardexPago::create([
                    'estudiante_programa_id' => $estudianteProgramaId,
                    'cuota_id' => null, // ❌ NO asignar cuota
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
                $this->procesados++;

                Log::info("✅ Kardex creado exitosamente (strict mode)", [
                    'kardex_id' => $kardex->id,
                    'estudiante_programa_id' => $estudianteProgramaId,
                    'numero_boleta' => $boleta,
                    'monto' => $monto,
                    'fila' => $numeroFila,
                    'cuota_id' => 'NULL (strict import)'
                ]);

                $this->detalles[] = [
                    'accion' => 'pago_registrado',
                    'fila' => $numeroFila,
                    'carnet' => $carnet,
                    'nombre' => $nombreEstudiante,
                    'kardex_id' => $kardex->id,
                    'monto' => $monto,
                    'fecha_pago' => $fechaPago->toDateString()
                ];
            });
        } catch (\Throwable $ex) {
            Log::error("❌ Error en inserción fila {$numeroFila}", [
                'error' => $ex->getMessage(),
                'carnet' => $carnet,
                'file' => $ex->getFile(),
                'line' => $ex->getLine()
            ]);

            $this->errores[] = [
                'tipo' => 'ERROR_INSERCION',
                'fila' => $numeroFila,
                'carnet' => $carnet,
                'boleta' => $boleta ?? 'N/A',
                'error' => $ex->getMessage(),
                'trace' => config('app.debug') ? array_slice(explode("\n", $ex->getTraceAsString()), 0, 3) : null
            ];
        }
    }

    /**
     * Obtener estudiante_programa_id para un carnet dado
     */
    private function obtenerEstudianteProgramaId($carnet)
    {
        if (isset($this->estudiantesCache[$carnet])) {
            Log::debug("📋 Usando cache para carnet", ['carnet' => $carnet]);
            return $this->estudiantesCache[$carnet];
        }

        Log::info("🔍 Buscando prospecto por carnet", ['carnet' => $carnet]);

        // Buscar prospecto
        $prospecto = DB::table('prospectos')
            ->where(DB::raw("REPLACE(UPPER(carnet), ' ', '')"), '=', $carnet)
            ->first();

        if (!$prospecto) {
            Log::warning("❌ Prospecto no encontrado", ['carnet' => $carnet]);
            $this->estudiantesCache[$carnet] = null;
            return null;
        }

        Log::info("✅ Prospecto encontrado", [
            'carnet' => $carnet,
            'prospecto_id' => $prospecto->id,
            'nombre_completo' => $prospecto->nombre_completo
        ]);

        // Buscar estudiante_programa (tomar el más reciente)
        $estudiantePrograma = DB::table('estudiante_programa')
            ->where('prospecto_id', $prospecto->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$estudiantePrograma) {
            Log::warning("❌ No hay programa para este prospecto", [
                'carnet' => $carnet,
                'prospecto_id' => $prospecto->id
            ]);
            $this->estudiantesCache[$carnet] = null;
            return null;
        }

        Log::info("✅ Estudiante programa encontrado", [
            'prospecto_id' => $prospecto->id,
            'estudiante_programa_id' => $estudiantePrograma->id
        ]);

        $this->estudiantesCache[$carnet] = $estudiantePrograma->id;
        return $estudiantePrograma->id;
    }

    /**
     * Log resumen final de la importación
     */
    private function logResumenFinal()
    {
        Log::info('=== ✅ PROCESAMIENTO COMPLETADO ===', [
            'total_rows' => $this->totalRows,
            'procesados' => $this->procesados,
            'kardex_creados' => $this->kardexCreados,
            'pagos_omitidos' => $this->pagosOmitidos,
            'total_monto' => round($this->totalAmount, 2),
            'errores' => count($this->errores),
            'advertencias' => count($this->advertencias),
            'timestamp' => now()->toDateTimeString()
        ]);

        Log::info('=' . str_repeat('=', 80));
        Log::info('🎯 RESUMEN FINAL DE IMPORTACIÓN (STRICT MODE)');
        Log::info('=' . str_repeat('=', 80));
        
        Log::info('✅ EXITOSOS', [
            'filas_procesadas' => $this->procesados,
            'kardex_creados' => $this->kardexCreados,
            'pagos_omitidos_duplicados' => $this->pagosOmitidos,
            'monto_total' => 'Q' . number_format($this->totalAmount, 2),
            'porcentaje_exito' => $this->totalRows > 0
                ? round(($this->procesados / $this->totalRows) * 100, 2) . '%'
                : '0%'
        ]);

        Log::info('⚠️ ADVERTENCIAS', [
            'total' => count($this->advertencias),
            'fechas_completadas' => collect($this->advertencias)->where('tipo', 'FECHA_COMPLETADA')->count(),
            'campos_opcionales_faltantes' => collect($this->advertencias)->where('tipo', 'CAMPOS_OPCIONALES_FALTANTES')->count()
        ]);

        $erroresCollection = collect($this->errores);
        Log::info('❌ ERRORES', [
            'total' => count($this->errores),
            'estudiantes_no_encontrados' => $erroresCollection->where('tipo', 'ESTUDIANTE_NO_ENCONTRADO')->count(),
            'datos_incompletos' => $erroresCollection->where('tipo', 'DATOS_INCOMPLETOS')->count(),
            'pago_duplicado' => $erroresCollection->where('tipo', 'PAGO_DUPLICADO')->count(),
            'error_insercion' => $erroresCollection->where('tipo', 'ERROR_INSERCION')->count()
        ]);

        Log::info('=' . str_repeat('=', 80));
    }

    /**
     * Método para obtener reporte detallado de éxitos
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
                'pagos_omitidos' => $this->pagosOmitidos,
            ],
            'por_estudiante' => $registrosExitosos->groupBy('carnet')->map(function ($registros, $carnet) {
                return [
                    'carnet' => $carnet,
                    'nombre' => $registros->first()['nombre'] ?? 'N/A',
                    'cantidad_pagos' => $registros->count(),
                    'monto_total' => round($registros->sum('monto'), 2),
                    'kardex_ids' => $registros->pluck('kardex_id')->toArray(),
                    'fechas_procesadas' => $registros->pluck('fecha_pago')->unique()->values()->toArray()
                ];
            })->values()->toArray(),
            'detalle_completo' => $registrosExitosos->sortBy('fila')->values()->toArray()
        ];
    }

    // =========================================================================
    // Helper methods for normalization
    // =========================================================================

    private function normalizarCarnet($carnet)
    {
        $normalizado = strtoupper(preg_replace('/\s+/', '', trim($carnet)));

        Log::debug('🎫 Carnet normalizado', [
            'original' => $carnet,
            'normalizado' => $normalizado
        ]);

        return $normalizado;
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
}
