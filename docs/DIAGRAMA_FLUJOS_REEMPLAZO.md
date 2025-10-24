# Diagrama Visual: Flujos de Reemplazo de Cuotas

## Flujo Normal (Sin Reemplazo)

```
┌─────────────────────────────────────────────────────────────────┐
│                    IMPORTACIÓN NORMAL                            │
└─────────────────────────────────────────────────────────────────┘

Excel File → PaymentHistoryImport → ┬→ Buscar Estudiante
                                     │
                                     ├→ Buscar Cuotas Existentes
                                     │
                                     ├→ Buscar Cuota Compatible
                                     │   ├─ Si encuentra: Usar cuota
                                     │   └─ Si no encuentra: Crear kardex sin cuota
                                     │
                                     └→ Crear Kardex de Pago
                                         └→ Actualizar cuota a "pagado"

⚠️ Problema: Si hay cuotas duplicadas o mal configuradas, se mantienen
```

---

## Flujo: Modo Reemplazo de Pendientes

```
┌─────────────────────────────────────────────────────────────────┐
│           MODO REEMPLAZO PENDIENTES (modoReemplazoPendientes)   │
└─────────────────────────────────────────────────────────────────┘

Excel File → PaymentHistoryImport(true, false) → ┬→ Buscar Estudiante
                                                  │
                                                  ├→ Buscar Cuotas Existentes
                                                  │
                                                  ├→ reemplazarCuotaPendiente()
                                                  │   │
                                                  │   ├─ Buscar cuota PENDIENTE
                                                  │   │  compatible (tolerancia 50%)
                                                  │   │
                                                  │   ├─ Actualizar a "pagado"
                                                  │   │  con fecha del pago
                                                  │   │
                                                  │   └─ Limpiar cache
                                                  │
                                                  └→ Crear Kardex de Pago
                                                      └→ Vincular con cuota actualizada

✅ Ventaja: No crea cuotas duplicadas, actualiza las existentes
✅ Ventaja: Mantiene estructura histórica
```

### Lógica de Coincidencia en reemplazarCuotaPendiente()

```
PRIORIDAD 1: Mensualidad Aprobada
┌───────────────────────────────────────┐
│ Cuota.monto ≈ Mensualidad_Aprobada    │
│ Tolerancia: 50% o Q100 (lo mayor)     │
│ Estado: PENDIENTE únicamente          │
└───────────────────────────────────────┘
          │ SI encuentra
          ↓
     ACTUALIZAR CUOTA
          │
          ├→ estado = "pagado"
          ├→ paid_at = fecha_pago
          └→ Limpiar cache
          
          │ NO encuentra
          ↓

PRIORIDAD 2: Monto de Pago
┌───────────────────────────────────────┐
│ Cuota.monto ≈ Monto_Pago              │
│ Tolerancia: 50% o Q100 (lo mayor)     │
│ Estado: PENDIENTE únicamente          │
└───────────────────────────────────────┘
          │ SI encuentra
          ↓
     ACTUALIZAR CUOTA
          
          │ NO encuentra
          ↓

PRIORIDAD 3: Primera Pendiente
┌───────────────────────────────────────┐
│ Primera cuota PENDIENTE disponible    │
│ Sin validación de monto               │
└───────────────────────────────────────┘
          │ SI encuentra
          ↓
     ACTUALIZAR CUOTA
          
          │ NO encuentra
          ↓
     Continuar con lógica normal
```

---

## Flujo: Modo Reemplazo Total

```
┌─────────────────────────────────────────────────────────────────┐
│              MODO REEMPLAZO TOTAL (modoReemplazo)                │
└─────────────────────────────────────────────────────────────────┘

Excel File → PaymentHistoryImport(false, true)
                    │
                    ├→ Agrupar pagos por carnet
                    │
                    └→ Para cada carnet:
                        │
                        ├─ PaymentReplaceService.purgeAndRebuildForCarnet()
                        │   │
                        │   ├─ Buscar/Crear Estudiante
                        │   │
                        │   ├─ Inferir datos del Excel:
                        │   │  ├─ Fecha inicio (min fecha_pago)
                        │   │  ├─ Mensualidad (moda)
                        │   │  └─ Inscripción (concepto "inscrip")
                        │   │
                        │   ├─ PURGE (Eliminar):
                        │   │  ├─ ❌ Conciliaciones
                        │   │  ├─ ❌ Kardex de pagos
                        │   │  └─ ❌ Cuotas
                        │   │
                        │   └─ REBUILD (Reconstruir):
                        │      │
                        │      ├─ Obtener configuración:
                        │      │  ├─ estudiante_programa
                        │      │  ├─ precio_programa
                        │      │  └─ Inferencias Excel
                        │      │
                        │      ├─ Crear Cuota 0 (si hay inscripción > 0)
                        │      │  └─ numero_cuota = 0, estado = "pendiente"
                        │      │
                        │      └─ Crear Cuotas 1..N
                        │         └─ numero_cuota = 1..duracion_meses
                        │
                        └─ Procesar pagos normalmente
                           └─ Vincular con cuotas recién creadas

⚠️  ADVERTENCIA: Proceso DESTRUCTIVO - elimina TODOS los datos existentes
✅ Ventaja: Garantiza estructura limpia sin duplicados
✅ Ventaja: Reconstruye desde configuración actual del programa
```

### Flujo Detallado de PURGE

```
estudiante_programa_id = 162
        │
        ├─ PASO 1: Buscar kardex_pagos
        │  SELECT id FROM kardex_pagos WHERE estudiante_programa_id = 162
        │  → IDs: [1001, 1002, 1003, ...]
        │
        ├─ PASO 2: Eliminar conciliaciones
        │  DELETE FROM reconciliation_records 
        │  WHERE kardex_pago_id IN (1001, 1002, 1003, ...)
        │  ✓ Eliminado: 12 registros
        │
        ├─ PASO 3: Eliminar kardex
        │  DELETE FROM kardex_pagos WHERE estudiante_programa_id = 162
        │  ✓ Eliminado: 12 registros
        │
        └─ PASO 4: Eliminar cuotas
           DELETE FROM cuotas_programa_estudiante 
           WHERE estudiante_programa_id = 162
           ✓ Eliminado: 40 cuotas
```

### Flujo Detallado de REBUILD

```
Obtener Configuración:
┌──────────────────────────────────────────────────────────────────┐
│ 1. estudiante_programa                                           │
│    ├─ duracion_meses: 40                                         │
│    ├─ cuota_mensual: 800.00                                      │
│    ├─ fecha_inicio: 2020-01-15                                   │
│    └─ inscripcion: 500.00 (si existe el campo)                   │
│                                                                   │
│ 2. precio_programa (si falta en EP)                              │
│    ├─ meses: 40                                                  │
│    ├─ cuota_mensual: 800.00                                      │
│    └─ inscripcion: 500.00                                        │
│                                                                   │
│ 3. Inferencias Excel (último recurso)                            │
│    ├─ fecha_inicio: min(fecha_pago) = 2020-02-10                │
│    ├─ mensualidad: moda(mensualidad_aprobada) = 800.00          │
│    └─ inscripcion: moda(monto donde concepto = "inscrip") = 500 │
└──────────────────────────────────────────────────────────────────┘
                            ↓
                    Generar Cuotas:
                            
┌─────────────────────────────────────────────────────────────────┐
│ SI inscripcion > 0:                                             │
│   Cuota 0 (Inscripción)                                         │
│   ├─ numero_cuota: 0                                            │
│   ├─ fecha_vencimiento: 2020-01-15 (fecha inicio)               │
│   ├─ monto: 500.00                                              │
│   └─ estado: "pendiente"                                        │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ Cuotas Regulares 1..40                                          │
│   Para i = 1 hasta duracion_meses:                              │
│     ├─ numero_cuota: i                                          │
│     ├─ fecha_vencimiento: fecha_inicio + (i-1) meses            │
│     ├─ monto: 800.00                                            │
│     └─ estado: "pendiente"                                      │
│                                                                  │
│   Resultado:                                                     │
│   ├─ Cuota 1: 2020-01-15, Q800.00                               │
│   ├─ Cuota 2: 2020-02-15, Q800.00                               │
│   ├─ Cuota 3: 2020-03-15, Q800.00                               │
│   └─ ... hasta Cuota 40                                         │
└─────────────────────────────────────────────────────────────────┘
                            ↓
              INSERT INTO cuotas_programa_estudiante
                            ↓
                   ✅ 41 cuotas creadas
              (1 inscripción + 40 mensuales)
```

---

## Flujo: Ambos Modos Combinados

```
┌─────────────────────────────────────────────────────────────────┐
│    MODO COMBINADO (modoReemplazoPendientes + modoReemplazo)     │
└─────────────────────────────────────────────────────────────────┘

Excel File → PaymentHistoryImport(true, true)
                    │
                    ├─ FASE 1: Reemplazo Total
                    │  └─ Purge + Rebuild (como arriba)
                    │     └─ Cuotas limpias y reconstruidas
                    │
                    └─ FASE 2: Procesamiento de Pagos
                       └─ Con modoReemplazoPendientes activo
                          └─ Actualiza cuotas pendientes a pagadas

Flujo detallado:

1. Purge → Elimina TODA la data antigua (kardex, conciliaciones, cuotas)

2. Rebuild → Crea cuotas frescas desde configuración
   ├─ Cuota 0 (si hay inscripción)
   └─ Cuotas 1..N

3. Por cada pago en Excel:
   ├─ reemplazarCuotaPendiente() encuentra cuota pendiente
   ├─ Actualiza cuota a "pagado"
   └─ Crea kardex vinculado

✅ Resultado: Estructura completamente limpia con pagos correctamente asignados
```

---

## Prevención de Duplicados

### En generarCuotasSiFaltan()

```
┌──────────────────────────────────────────────────────────────┐
│ Antes de generar cuotas:                                     │
│                                                               │
│ 1. Verificar cuotas existentes                               │
│    SELECT COUNT(*) FROM cuotas_programa_estudiante           │
│    WHERE estudiante_programa_id = ?                          │
│                                                               │
│ 2. Si count > 0:                                             │
│    └─ ⚠️  Ya existen cuotas                                  │
│       └─ return false (no genera)                            │
│                                                               │
│ 3. Si count = 0:                                             │
│    └─ ✅ Generar cuotas                                       │
└──────────────────────────────────────────────────────────────┘
```

### En Kardex (Fingerprint)

```
Verificación de Duplicados de Kardex:

PASO 1: Por boleta + estudiante_programa_id
┌────────────────────────────────────────────┐
│ SELECT * FROM kardex_pagos                 │
│ WHERE numero_boleta = 'BOL001'             │
│   AND estudiante_programa_id = 162         │
└────────────────────────────────────────────┘
        │ Si existe
        ↓
    ⚠️  DUPLICADO - omitir

PASO 2: Por fingerprint (más preciso)
┌────────────────────────────────────────────┐
│ fingerprint = hash(                        │
│   banco_normalizado +                      │
│   boleta_normalizada +                     │
│   estudiante_programa_id +                 │
│   fecha_pago                               │
│ )                                          │
│                                            │
│ SELECT * FROM kardex_pagos                 │
│ WHERE boleta_fingerprint = fingerprint     │
└────────────────────────────────────────────┘
        │ Si existe
        ↓
    ⚠️  DUPLICADO - omitir
    
        │ No existe
        ↓
    ✅ Crear kardex
```

---

## Comparación de Modos

| Aspecto | Normal | Reemplazo Pendientes | Reemplazo Total | Ambos Combinados |
|---------|--------|---------------------|----------------|------------------|
| **Destructivo** | No | No | ⚠️  Sí | ⚠️  Sí |
| **Modifica cuotas existentes** | No | Sí (pendientes→pagadas) | Elimina todas | Elimina + recrea |
| **Previene duplicados** | Parcial | ✅ Sí | ✅ Sí | ✅ Sí |
| **Mantiene historial** | Sí | Sí | No | No |
| **Requiere backup** | No | No | ⚠️  Sí | ⚠️  Sí |
| **Velocidad** | Rápida | Media | Lenta | Lenta |
| **Uso recomendado** | Primera vez | Actualizaciones | Corrección masiva | Migración completa |

---

## Logs Esperados

### Reemplazo de Pendientes

```
🔄 Modo reemplazo activo: buscando cuota pendiente para reemplazar
   estudiante_programa_id: 162
   fila: 5
   monto_pago: 800.00
   mensualidad_aprobada: 800.00

✅ Cuota pendiente encontrada por mensualidad aprobada
   cuota_id: 1234
   numero_cuota: 3
   monto_cuota: 800.00
   mensualidad_aprobada: 800.00

🔄 Reemplazando cuota pendiente con pago
   cuota_id: 1234
   estado_anterior: pendiente
   estado_nuevo: pagado
   fecha_pago: 2023-06-15
```

### Reemplazo Total

```
🔄 MODO REEMPLAZO ACTIVO: Se eliminará y reconstruirá todo para cada estudiante

🔄 [Reemplazo] Procesando carnet ASM2020103
   cantidad_pagos: 40

🧹 [Replace] PURGE EP 162 (Licenciatura en Contabilidad)
   • conciliaciones eliminadas: 12
   • kardex eliminados: 12
   • cuotas eliminadas: 40

🔧 [Replace] Rebuild cuotas
   ep_id: 162
   duracion_meses: 40
   cuota_mensual: 800.00
   fecha_inicio: 2020-01-15
   inscripcion: 500.00

✅ [Replace] Malla reconstruida (incluye cuota 0 si aplica)
   ep_id: 162
   cuota_mensual: 800.00
   inscripcion: 500.00

✅ [Reemplazo] Carnet ASM2020103 listo para importación
   cantidad_pagos: 40
```

---

## Decisión de Modo a Usar

```
┌─────────────────────────────────────────────────────────────────┐
│                  ¿QUÉ MODO DEBO USAR?                           │
└─────────────────────────────────────────────────────────────────┘

¿Primera importación de estudiante?
    ├─ SÍ → Usar modo NORMAL
    └─ NO → Continuar...

¿Hay cuotas duplicadas o incorrectas?
    ├─ SÍ → Usar modo REEMPLAZO TOTAL ⚠️
    └─ NO → Continuar...

¿Solo necesitas actualizar cuotas pendientes?
    ├─ SÍ → Usar modo REEMPLAZO PENDIENTES
    └─ NO → Continuar...

¿Migración completa desde sistema antiguo?
    ├─ SÍ → Usar AMBOS MODOS ⚠️
    └─ NO → Usar modo NORMAL
```
