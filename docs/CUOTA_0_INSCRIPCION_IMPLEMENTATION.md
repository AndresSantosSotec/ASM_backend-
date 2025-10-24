# Implementación de Cuota 0 – Inscripción en PaymentHistoryImport

## Resumen

Se ha implementado soporte completo para "Cuota 0 – Inscripción" en el sistema de importación de pagos históricos. Esto incluye:

1. **Detección automática** del monto de inscripción desde múltiples fuentes
2. **Generación de Cuota 0** durante el rebuild de programas en modo reemplazo
3. **Asignación prioritaria** de pagos de inscripción a la Cuota 0
4. **Compatibilidad total** con el flujo existente (TEMP → real, pagos parciales, etc.)

## Cambios Implementados

### 1. PaymentReplaceService (app/Imports/PaymentReplaceService.php)

#### Nuevo método: `inferInscripcion()`

```php
private function inferInscripcion(Collection $pagos): ?float
```

**Funcionalidad:**
- Detecta montos de inscripción en el Excel basándose en:
  - **Prioridad 1:** Pagos cuyo concepto contiene "inscrip" (inscripción, inscripción inicial, etc.)
  - **Prioridad 2:** Heurística conservadora para montos pequeños únicos
- Retorna la moda (valor más frecuente) de los montos detectados

**Ejemplo de uso:**
```php
$inscripcionInferida = $this->inferInscripcion($pagosEstudiante);
// Retorna: 500.00 si varios pagos tienen monto 500 con concepto "inscripcion"
```

#### Método actualizado: `rebuildCuotasFromProgram()`

**Nueva firma:**
```php
private function rebuildCuotasFromProgram(
    int $epId, 
    ?Carbon $fechaInicioInferida, 
    ?float $mensualidadInferida, 
    ?float $inscripcionInferida = null  // 👈 NUEVO
): void
```

**Lógica de detección de inscripción (en orden de prioridad):**

1. **Desde `estudiante_programa.inscripcion`** (si existe el campo)
2. **Desde `precio_programa.inscripcion`** (tabla tb_precios_programa)
3. **Desde inferencia del Excel** (parámetro $inscripcionInferida)

**Generación de Cuota 0:**
```php
if ($inscripcion !== null && $inscripcion > 0) {
    $rows[] = [
        'estudiante_programa_id' => $epId,
        'numero_cuota'           => 0,        // 👈 Cuota 0
        'fecha_vencimiento'      => $fechaInicio->toDateString(),
        'monto'                  => $inscripcion,
        'estado'                 => 'pendiente',
        'created_at'             => now(),
        'updated_at'             => now(),
    ];
}
```

### 2. PaymentHistoryImport (app/Imports/PaymentHistoryImport.php)

#### Nuevas propiedades de clase

```php
private bool $modoReemplazo = false;
private ?PaymentReplaceService $replaceService = null;
private ?array $rowContext = null;  // Para pasar concepto a buscarCuotaFlexible
```

#### Constructor actualizado

```php
public function __construct(
    int $uploaderId, 
    string $tipoArchivo = 'cardex_directo', 
    bool $modoReemplazo = false  // 👈 NUEVO parámetro
)
```

**Uso:**
```php
// Modo normal (sin reemplazo)
$import = new PaymentHistoryImport($uploaderId);

// Modo reemplazo (purge + rebuild)
$import = new PaymentHistoryImport($uploaderId, 'cardex_directo', true);
```

#### Modo Reemplazo en `collection()`

Antes de procesar pagos, si `$modoReemplazo === true`:

```php
foreach ($pagosPorCarnet as $carnet => $pagosEstudiante) {
    $resolver = function (string $carnetN, $row) {
        return $this->obtenerProgramasEstudiante($carnetN, $row);
    };
    
    $this->replaceService->purgeAndRebuildForCarnet(
        $resolver, 
        $carnetNorm, 
        $pagosEstudiante, 
        $this->uploaderId
    );
}
```

#### PRIORIDAD 0 en `buscarCuotaFlexible()`

**Nueva lógica antes de las prioridades existentes:**

```php
// === PRIORIDAD 0: CUOTA 0 (Inscripción) ===
$cuotaInscripcion = $cuotasPendientes->first(function ($c) {
    return (int)$c->numero_cuota === 0;
});

if ($cuotaInscripcion && $cuotaInscripcion->estado === 'pendiente') {
    // a) Detectar por concepto
    $esInscripcionPorConcepto = str_contains(
        strtolower($this->rowContext['concepto'] ?? ''), 
        'inscrip'
    );
    
    // b) Detectar por monto cercano (tolerancia 30% o mín Q100)
    $toleranciaIns = max(100, $cuotaInscripcion->monto * 0.30);
    $esInscripcionPorMonto = abs($cuotaInscripcion->monto - $montoPago) <= $toleranciaIns;
    
    if ($esInscripcionPorConcepto || $esInscripcionPorMonto) {
        return $cuotaInscripcion;  // 👈 Se asigna a Cuota 0
    }
}
```

**Tolerancias aplicadas:**
- **30% del monto de inscripción** o **mínimo Q100**
- Ejemplo: Para inscripción de Q500, tolerancia = max(100, 500*0.30) = Q150
- Un pago de Q400-Q600 se considera inscripción

#### Contexto de fila (rowContext)

En `procesarPagoIndividual()`:

```php
$this->rowContext = [
    'concepto' => $concepto,
    'mes_pago' => $mesPago,
    'mes_inicio' => $mesInicio
];

// ... procesamiento ...

unset($this->rowContext);  // Limpiar después de usar
```

#### Detalles ampliados en reportes

```php
$this->detalles[] = [
    // ... campos existentes ...
    'numero_cuota' => $cuota ? $cuota->numero_cuota : null,       // 👈 NUEVO
    'es_inscripcion' => $cuota ? ((int)$cuota->numero_cuota === 0) : false,  // 👈 NUEVO
];
```

## Flujo Completo de Importación con Cuota 0

### Escenario 1: Modo Reemplazo con Inscripción

**Excel con datos:**
```
Carnet      | Concepto           | Monto  | Fecha
ASM2024001  | Inscripción        | 500.00 | 2024-01-15
ASM2024001  | Cuota Mensual      | 800.00 | 2024-02-15
ASM2024001  | Cuota Mensual      | 800.00 | 2024-03-15
```

**Flujo:**

1. **Inferencia** (PaymentReplaceService):
   - Detecta concepto "Inscripción" → monto 500.00
   - `inferInscripcion()` retorna Q500.00

2. **Purge + Rebuild**:
   - Elimina cuotas/kardex/conciliaciones anteriores
   - Consulta `precio_programa` → inscripcion = Q500
   - **Crea Cuota 0** con monto Q500 y fecha = fecha_inicio

3. **Asignación de Pagos**:
   - **Pago 1** (Q500, "Inscripción"):
     - PRIORIDAD 0 detecta concepto "inscrip"
     - Asigna a **Cuota 0** ✅
     - Marca Cuota 0 como "pagado"
   
   - **Pago 2** (Q800, "Cuota Mensual"):
     - PRIORIDAD 1 o 2 busca cuota mensual
     - Asigna a **Cuota 1** ✅
   
   - **Pago 3** (Q800, "Cuota Mensual"):
     - Asigna a **Cuota 2** ✅

### Escenario 2: Pago Parcial de Inscripción

**Excel:**
```
Carnet      | Concepto           | Monto  
ASM2024002  | Inscripción Inicial| 300.00  
```

**Precio Programa:** inscripcion = Q500

**Flujo:**

1. **Rebuild** crea Cuota 0 de Q500
2. **Asignación**:
   - PRIORIDAD 0 detecta "inscrip" en concepto
   - Calcula: porcentaje = 300/500 = 60% ≥ 30% ✅
   - **Asigna a Cuota 0** con advertencia:

```php
$this->advertencias[] = [
    'tipo' => 'PAGO_PARCIAL_INSCRIPCION',
    'fila' => 2,
    'advertencia' => 'Pago parcial de inscripción: Q300.00 de Q500.00 (60.0%)',
    'cuota_id' => 123
];
```

### Escenario 3: Sin Inscripción

**Programa sin inscripción en `precio_programa`**

**Flujo:**

1. `inferInscripcion()` no encuentra concepto "inscrip" → retorna null
2. `precio_programa.inscripcion` = 0
3. **NO se crea Cuota 0** ✅ (comportamiento correcto)
4. Cuotas regulares se generan normalmente (1..N)

## Logs y Debugging

### Logs de Rebuild

```
🔧 [Replace] Rebuild cuotas
{
    "ep_id": 456,
    "duracion_meses": 12,
    "cuota_mensual": 800.00,
    "fecha_inicio": "2024-01-15",
    "inscripcion": 500.00  // 👈 Se muestra si hay inscripción
}

✅ [Replace] Malla reconstruida (incluye cuota 0 si aplica)
{
    "ep_id": 456,
    "cuota_mensual": 800.00,
    "inscripcion": 500.00
}
```

### Logs de Asignación de Pago

```
✅ Cuota 0 (inscripción) detectada como match
{
    "cuota_id": 789,
    "monto_cuota": 500.00,
    "monto_pago": 500.00,
    "por_concepto": true,     // 👈 Detectado por concepto
    "por_monto": true,        // 👈 Y también por monto
    "tolerancia": 150.00
}
```

### Logs de Detalles de Éxito

```php
[
    'accion' => 'pago_registrado',
    'cuota_id' => 789,
    'numero_cuota' => 0,           // 👈 Indica Cuota 0
    'es_inscripcion' => true,      // 👈 Flag de inscripción
    'monto' => 500.00,
]
```

## Compatibilidad y Migración

### Compatibilidad con TEMP → Real

✅ **Totalmente compatible**

El flujo existente:
1. Detecta `plan_estudios` en Excel
2. Actualiza programa TEMP a real
3. **Luego** ejecuta purge + rebuild (con Cuota 0 si aplica)
4. Asigna pagos del Excel

### Compatibilidad con Modo Normal (sin reemplazo)

✅ **No afecta el flujo normal**

Si `modoReemplazo = false`:
- No se ejecuta purge + rebuild
- Cuotas existentes se mantienen
- Si existe Cuota 0 manual, PRIORIDAD 0 la detectará y asignará correctamente

### Migración de Datos Históricos

Para importar datos históricos con inscripción:

```php
$importer = new PaymentHistoryImport(
    uploaderId: $userId,
    tipoArchivo: 'cardex_directo',
    modoReemplazo: true  // 👈 Activar modo reemplazo
);

Excel::import($importer, $file);
```

## Configuración y Ajustes

### Tolerancias

Actualmente hardcodeadas (pueden moverse a config/env):

```php
// PaymentHistoryImport.php línea ~817
$toleranciaIns = max(100, $cuotaInscripcion->monto * 0.30);
```

**Para ajustar:**
```php
$toleranciaIns = max(
    config('payment.inscripcion_tolerance_min', 100),
    $cuotaInscripcion->monto * config('payment.inscripcion_tolerance_percent', 0.30)
);
```

### Detección de Concepto

Actualmente busca substring "inscrip":

```php
str_contains($conceptoRaw, 'inscrip')
```

**Para agregar más términos:**
```php
$terminos = ['inscrip', 'matricula', 'registro', 'ingreso'];
$esInscripcion = collect($terminos)->contains(fn($t) => str_contains($conceptoRaw, $t));
```

## Criterios de Aceptación ✅

- [x] Si programa tiene inscripción > 0, rebuild crea Cuota 0
- [x] Pagos con concepto "inscrip" se asignan a Cuota 0
- [x] Pagos con monto cercano a inscripción se asignan a Cuota 0
- [x] Pagos parciales de Cuota 0 se registran con advertencia
- [x] Modo reemplazo elimina todo y reconstruye con Cuota 0
- [x] TEMP → real funciona correctamente con Cuota 0
- [x] Logs muestran número de cuota y flag de inscripción
- [x] Compatible con flujo existente

## Pruebas Recomendadas

### 1. Prueba Básica con Inscripción

**Crear precio_programa:**
```sql
INSERT INTO tb_precios_programa (programa_id, inscripcion, cuota_mensual, meses)
VALUES (1, 500.00, 800.00, 12);
```

**Excel:**
```
Carnet,Concepto,Monto
ASM2024TEST,Inscripción,500.00
ASM2024TEST,Mensualidad,800.00
```

**Verificar:**
```sql
SELECT numero_cuota, monto, estado 
FROM cuotas_programa_estudiante 
WHERE estudiante_programa_id = ?
ORDER BY numero_cuota;

-- Resultado esperado:
-- numero_cuota | monto  | estado
-- 0            | 500.00 | pagado  ✅
-- 1            | 800.00 | pagado  ✅
-- 2            | 800.00 | pendiente
```

### 2. Prueba con Pago Parcial

**Excel:**
```
Carnet,Concepto,Monto
ASM2024TEST,Inscripción,300.00
```

**Verificar logs:**
```
⚠️ PAGO_PARCIAL_INSCRIPCION
Pago parcial de inscripción: Q300.00 de Q500.00 (60.0%)
```

### 3. Prueba sin Inscripción

**precio_programa.inscripcion = 0**

**Verificar:**
```sql
SELECT numero_cuota FROM cuotas_programa_estudiante WHERE estudiante_programa_id = ?;

-- Resultado esperado: 1, 2, 3, ... (sin cuota 0) ✅
```

## Troubleshooting

### Problema: Cuota 0 no se crea

**Verificar:**
1. `precio_programa.inscripcion` > 0?
2. `estudiante_programa.inscripcion` > 0?
3. Excel tiene concepto "inscrip"?
4. Modo reemplazo está activado?

### Problema: Pago no se asigna a Cuota 0

**Verificar:**
1. Cuota 0 está en estado "pendiente"?
2. Concepto contiene "inscrip"?
3. Monto está dentro de tolerancia (30% o Q100)?

### Problema: Pago se asigna incorrectamente

**Revisar logs:**
```
✅ Cuota 0 (inscripción) detectada como match
por_concepto: true/false
por_monto: true/false
```

## Limitaciones Conocidas

1. **Pagos parciales:** Se marca cuota como "pagado" incluso si no cubre 100%
   - Recomendación futura: Implementar campo `saldo_pendiente` en cuotas

2. **Tolerancia fija:** 30% hardcodeada
   - Recomendación: Mover a config/env

3. **Un solo concepto:** Solo busca "inscrip"
   - Recomendación: Lista configurable de términos

## Próximos Pasos Sugeridos

1. **Agregar campo `saldo_pendiente`** en `cuotas_programa_estudiante`
2. **Migrar tolerancias a configuración**
3. **Expandir detección de conceptos** (matricula, registro, etc.)
4. **Agregar tests automatizados** para todos los escenarios
5. **Dashboard de inscripciones** para visualizar Cuota 0 pendientes/pagadas

## Referencias

- **Archivo:** `app/Imports/PaymentReplaceService.php`
- **Archivo:** `app/Imports/PaymentHistoryImport.php`
- **Issue:** Cuota 0 (Inscripción) - Regeneración y Asignación Prioritaria
- **Documentación relacionada:** `TOLERANCE_QUICK_REF.md`, `PAYMENT_HISTORY_IMPORT_LOGGING_GUIDE.md`
