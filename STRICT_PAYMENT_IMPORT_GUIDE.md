# 📋 Guía de Importación Estricta de Pagos

## 🎯 Objetivo

El proceso de importación estricta de pagos (`PaymentHistoryImport`) ha sido modificado para que **únicamente inserte en la base de datos los pagos presentes en el archivo Excel**, sin generar cuotas adicionales ni modificar cuotas existentes.

## ✨ Características Principales

### 1. ❌ Eliminaciones (Comportamientos Removidos)

- **NO genera cuotas** en `cuotas_programa_estudiante`
- **NO ejecuta** `buscarCuotaFlexible()`
- **NO ejecuta** `actualizarCuotaYConciliar()`
- **NO crea** registros en `ReconciliationRecord`
- **NO crea** `PrecioPrograma` ni actualiza `estudiante_programa`

### 2. ✅ Funcionalidades Implementadas

#### Carga Estricta de Datos
- Cada fila del Excel representa exactamente un pago que se inserta en `kardex_pagos`
- No se buscan coincidencias con `cuotas_programa_estudiante`
- No se actualizan cuotas existentes
- Solo se registra el pago directamente

#### Validación y Normalización de Fechas
Si un campo `fecha_pago` está vacío o nulo:
1. Tomar la última fecha válida encontrada en filas anteriores del mismo estudiante (carnet)
2. Si no existe una fecha previa, usar la fecha actual (`Carbon::now()`)
3. Registrar advertencia: `FECHA_COMPLETADA`

#### Valores por Defecto para Campos Opcionales
```php
banco: 'NO ESPECIFICADO'
concepto: 'PAGO'
tipo_pago: 'MENSUAL'
mes_pago: 'SIN_MES'
año: Carbon::now()->year
```

#### Validación de Duplicados
Antes de insertar, valida que no exista otro registro con:
- `numero_boleta` + `estudiante_programa_id` (que corresponde al carnet) + `fecha_pago`

Si existe, se registra error tipo `PAGO_DUPLICADO` y se omite la inserción.

**Nota**: La validación interna usa `estudiante_programa_id` porque es la clave foránea en la tabla, pero conceptualmente corresponde al `carnet` del estudiante.

## 📊 Estructura del Archivo Excel

### Campos Requeridos
| Campo | Descripción | Requerido |
|-------|-------------|-----------|
| `carnet` | Identificador único del estudiante | ✅ SÍ |
| `nombre_estudiante` | Nombre completo del estudiante | ✅ SÍ |
| `numero_boleta` | Número de boleta bancaria o referencia | ✅ SÍ |
| `monto` | Monto pagado (numérico) | ✅ SÍ |
| `fecha_pago` | Fecha del pago (formato YYYY-MM-DD) | ✅ SÍ |

### Campos Opcionales
| Campo | Descripción | Valor por Defecto |
|-------|-------------|-------------------|
| `banco` | Banco donde se realizó el pago | `NO ESPECIFICADO` |
| `concepto` | Concepto del pago | `PAGO` |
| `tipo_pago` | Tipo de pago | `MENSUAL` |
| `mes_pago` | Mes al que corresponde el pago | `SIN_MES` |
| `ano` o `año` | Año al que corresponde el pago | Año actual |
| `plan_estudios` | Nombre o abreviatura del plan | (informativo) |
| `estatus` | Estado actual del estudiante | (informativo) |

## 🔄 Proceso de Importación

### 1. Validación de Estructura
```php
✅ Verifica que el archivo tenga las columnas requeridas
✅ Detecta columnas faltantes y genera error ESTRUCTURA_INVALIDA
```

### 2. Agrupación por Carnet
```php
✅ Agrupa todos los pagos por estudiante (carnet)
✅ Permite procesar fechas en orden para rellenar vacíos
```

### 3. Búsqueda de Estudiante
```php
✅ Busca prospecto por carnet
✅ Busca estudiante_programa del prospecto
❌ Si no encuentra, registra error ESTUDIANTE_NO_ENCONTRADO
```

### 4. Procesamiento de Fechas
```php
$ultimaFechaValida = null;

foreach ($pagos as $pago) {
    if (empty($pago['fecha_pago'])) {
        if ($ultimaFechaValida) {
            $pago['fecha_pago'] = $ultimaFechaValida;
            // Registrar advertencia FECHA_COMPLETADA
        } else {
            $pago['fecha_pago'] = Carbon::now();
            // Registrar advertencia FECHA_COMPLETADA
        }
    } else {
        $ultimaFechaValida = Carbon::parse($pago['fecha_pago']);
    }
}
```

### 5. Validación de Datos
```php
✅ Validar que boleta no esté vacía
✅ Validar que monto sea numérico y mayor a 0
✅ Validar que fecha_pago sea válida después del ajuste
❌ Si falla, registra error DATOS_INCOMPLETOS
```

### 6. Verificación de Duplicado
```php
✅ Busca kardex_pago con mismo:
   - estudiante_programa_id
   - numero_boleta
   - fecha_pago
❌ Si existe, registra error PAGO_DUPLICADO y omite inserción
```

### 7. Inserción Directa
```sql
INSERT INTO kardex_pagos (
    estudiante_programa_id,
    numero_boleta,
    monto_pagado,
    fecha_pago,
    banco,
    concepto,  -- incluido en observaciones
    tipo_pago,  -- incluido en observaciones
    mes_pago,   -- incluido en observaciones
    anio,       -- incluido en observaciones
    observaciones,
    estado_pago,
    cuota_id,  -- SIEMPRE NULL en modo estricto
    created_at,
    updated_at
) VALUES (...);
```

**Nota Importante:** `cuota_id` siempre es `NULL` en el modo de importación estricta.

## 📋 Tipos de Errores y Advertencias

### Errores (Bloquean la inserción)
| Tipo | Descripción | Solución |
|------|-------------|----------|
| `ARCHIVO_VACIO` | El archivo no contiene datos | Verificar que el Excel tenga datos |
| `ESTRUCTURA_INVALIDA` | Faltan columnas requeridas | Agregar columnas faltantes |
| `ESTUDIANTE_NO_ENCONTRADO` | No existe prospecto o programa | Verificar que el carnet sea válido |
| `DATOS_INCOMPLETOS` | Faltan campos críticos | Completar boleta, monto o fecha |
| `PAGO_DUPLICADO` | Ya existe un pago idéntico | Revisar si es duplicado real |
| `ERROR_INSERCION` | Error al insertar en BD | Revisar logs de base de datos |

### Advertencias (No bloquean la inserción)
| Tipo | Descripción | Acción |
|------|-------------|--------|
| `FECHA_COMPLETADA` | Fecha vacía fue rellenada | Informativo, pago se inserta |
| `CAMPOS_OPCIONALES_FALTANTES` | Campos opcionales usaron defaults | Informativo, pago se inserta |

## 📈 Resumen de Logs Esperado

```
=== ✅ IMPORTACIÓN FINALIZADA ===
Filas procesadas: 152
Pagos insertados: 152
Pagos omitidos (duplicados): 4
Errores: 2 (Estudiante no encontrado)
Advertencias: 3 (Fechas completadas automáticamente)
Monto total procesado: Q213,400.00
```

## 🔧 Uso en Código

```php
use App\Imports\PaymentHistoryImport;
use Maatwebsite\Excel\Facades\Excel;

// Crear instancia del importador
$import = new PaymentHistoryImport(
    uploaderId: auth()->id(),
    tipoArchivo: 'cardex_directo'
);

// Ejecutar importación
Excel::import($import, $request->file('archivo'));

// Obtener resumen de resultados
$resumen = [
    'procesados' => $import->procesados,
    'kardex_creados' => $import->kardexCreados,
    'pagos_omitidos' => $import->pagosOmitidos,
    'errores' => $import->errores,
    'advertencias' => $import->advertencias,
    'total_monto' => $import->totalAmount
];

// Obtener reporte detallado de éxitos
$reporteExitos = $import->getReporteExitos();
```

## ✅ Tests Implementados

- ✅ Constructor inicializa modo estricto correctamente
- ✅ Normalización de carnet (espacios y mayúsculas)
- ✅ Normalización de monto (símbolos de moneda)
- ✅ Normalización de fecha (Excel numérico y string)
- ✅ Normalización de boleta (boletas compuestas)
- ✅ Validación de columnas Excel (detecta faltantes)
- ✅ Reporte de éxitos cuando no hay detalles
- ✅ Fingerprint incluye estudiante y fecha (anti-colisión)
- ✅ Fingerprint distingue diferentes fechas

## 🚀 Mejoras Implementadas vs Versión Anterior

| Característica | Versión Anterior | Versión Estricta |
|----------------|------------------|------------------|
| Generación automática de cuotas | ✅ Sí | ❌ No |
| Búsqueda de cuotas | ✅ Sí | ❌ No |
| Actualización de cuotas | ✅ Sí | ❌ No |
| Reconciliación | ✅ Sí | ❌ No |
| Inserción directa | ❌ No | ✅ Sí |
| Rellenado de fechas | ❌ No | ✅ Sí |
| Valores por defecto | ⚠️ Parcial | ✅ Completo |
| Validación de duplicados | ⚠️ Fingerprint | ✅ Boleta+Carnet+Fecha |

## 📝 Notas Importantes

1. **Modo Estricto**: Esta implementación NO modifica cuotas existentes ni genera nuevas cuotas.
2. **Únicamente Inserción**: Solo se insertan registros en `kardex_pagos`.
3. **Sin Reconciliación**: No se crean registros en `reconciliation_records`.
4. **Cuota ID Nulo**: Todos los pagos tienen `cuota_id = NULL`.
5. **Caching**: Se usa caché interno para estudiantes para mejorar performance.

## 🔍 Troubleshooting

### Problema: "ESTUDIANTE_NO_ENCONTRADO"
**Solución**: Verificar que:
- El carnet existe en la tabla `prospectos`
- El prospecto tiene al menos un registro en `estudiante_programa`

### Problema: "PAGO_DUPLICADO"
**Solución**: Verificar que:
- No exista ya un pago con el mismo `numero_boleta` + `estudiante_programa_id` + `fecha_pago`
  (el `estudiante_programa_id` se obtiene del `carnet` del estudiante)
- Si es duplicado real, eliminar del Excel
- Si no es duplicado, verificar que los datos sean correctos

### Problema: "ESTRUCTURA_INVALIDA"
**Solución**: Verificar que:
- El Excel tenga las columnas requeridas: `carnet`, `nombre_estudiante`, `numero_boleta`, `monto`, `fecha_pago`
- Los nombres de las columnas coincidan exactamente (sin espacios extras)
- La primera fila del Excel contenga los encabezados

## 📞 Soporte

Para más información o soporte, consultar:
- Logs de Laravel en `storage/logs/laravel.log`
- Tests unitarios en `tests/Unit/PaymentHistoryImportTest.php`
- Código fuente en `app/Imports/PaymentHistoryImport.php`
