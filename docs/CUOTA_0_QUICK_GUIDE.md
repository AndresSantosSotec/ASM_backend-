# Cuota 0 (Inscripción) - Guía Rápida

## ¿Qué es Cuota 0?

La **Cuota 0** es una cuota especial que representa el pago de inscripción o matrícula de un estudiante en un programa. Se genera automáticamente cuando el programa tiene un monto de inscripción definido.

## Características Principales

✅ **Generación automática** cuando existe monto de inscripción  
✅ **Asignación prioritaria** de pagos marcados como inscripción  
✅ **Detección inteligente** por concepto y monto  
✅ **Soporte de pagos parciales** con advertencias  
✅ **Compatible** con modo reemplazo y flujo normal  

## Cómo Usar

### 1. Importación Normal (Sin Reemplazo)

```php
use App\Imports\PaymentHistoryImport;
use Maatwebsite\Excel\Facades\Excel;

$import = new PaymentHistoryImport($userId);
Excel::import($import, $file);
```

**Comportamiento:**
- No afecta cuotas existentes
- Si existe Cuota 0 pendiente, se asignará automáticamente

### 2. Importación con Reemplazo (Purge + Rebuild)

```php
$import = new PaymentHistoryImport(
    uploaderId: $userId,
    tipoArchivo: 'cardex_directo',
    modoReemplazo: true  // 👈 Activar modo reemplazo
);

Excel::import($import, $file);
```

**Comportamiento:**
1. Elimina cuotas, kardex y conciliaciones anteriores
2. Reconstruye malla de cuotas desde cero
3. **Crea Cuota 0** si el programa tiene inscripción
4. Importa pagos del Excel y asigna correctamente

## Detección de Inscripción

### Por Concepto

El sistema detecta automáticamente pagos de inscripción si el campo **concepto** contiene:

- `inscripcion`
- `inscripción`
- `Inscripción Inicial`
- Cualquier variante con "inscrip"

**Ejemplo Excel:**
```csv
Carnet,Concepto,Monto,Fecha
ASM2024001,Inscripción,500.00,2024-01-15
ASM2024001,Cuota Enero,800.00,2024-02-15
```

### Por Monto Cercano

Si el monto del pago está cerca del monto de inscripción (tolerancia 30% o mínimo Q100):

**Ejemplo:**
- Inscripción configurada: Q500
- Tolerancia: max(100, 500 × 0.30) = Q150
- Rango aceptado: Q350 - Q650

## Fuentes de Monto de Inscripción

El sistema busca el monto de inscripción en este orden:

1. **estudiante_programa.inscripcion** (campo en tabla)
2. **precio_programa.inscripcion** (tabla tb_precios_programa)
3. **Inferencia del Excel** (pagos con concepto "inscrip")

## Estructura de Cuota 0

```sql
SELECT * FROM cuotas_programa_estudiante 
WHERE numero_cuota = 0;
```

| Campo              | Valor                    |
|--------------------|--------------------------|
| numero_cuota       | 0                        |
| monto              | (monto de inscripción)   |
| fecha_vencimiento  | (fecha inicio programa)  |
| estado             | pendiente/pagado         |

## Ejemplos de Uso

### Ejemplo 1: Inscripción Normal

**Configuración:**
```sql
-- En tb_precios_programa
INSERT INTO tb_precios_programa (programa_id, inscripcion, cuota_mensual, meses)
VALUES (1, 500.00, 800.00, 12);
```

**Excel:**
```csv
Carnet,Nombre,Concepto,Monto,Fecha
ASM2024001,Juan Pérez,Inscripción,500.00,2024-01-15
ASM2024001,Juan Pérez,Mensualidad,800.00,2024-02-15
```

**Resultado:**
```
Cuota 0: Q500.00 → PAGADO ✅
Cuota 1: Q800.00 → PAGADO ✅
Cuota 2: Q800.00 → Pendiente
...
```

### Ejemplo 2: Pago Parcial de Inscripción

**Excel:**
```csv
Carnet,Concepto,Monto
ASM2024002,Inscripción,300.00
```

**Configuración:** inscripcion = Q500

**Resultado:**
```
⚠️ Advertencia: Pago parcial de inscripción
   - Pagado: Q300.00 de Q500.00 (60%)
   - Cuota 0 marcada como PAGADO
   - Discrepancia: Q200.00
```

### Ejemplo 3: Sin Concepto Explícito

**Excel:**
```csv
Carnet,Concepto,Monto
ASM2024003,Pago 1,500.00
```

**Configuración:** inscripcion = Q500

**Resultado:**
```
✅ Detectado por monto cercano
   - Diferencia: Q0.00 ≤ Q150 (tolerancia)
   - Asignado a Cuota 0
```

## Logs y Seguimiento

### Log de Creación de Cuota 0

```json
{
  "level": "info",
  "message": "🔧 [Replace] Rebuild cuotas",
  "context": {
    "ep_id": 456,
    "duracion_meses": 12,
    "cuota_mensual": 800.00,
    "fecha_inicio": "2024-01-15",
    "inscripcion": 500.00
  }
}
```

### Log de Asignación a Cuota 0

```json
{
  "level": "info",
  "message": "✅ Cuota 0 (inscripción) detectada como match",
  "context": {
    "cuota_id": 789,
    "monto_cuota": 500.00,
    "monto_pago": 500.00,
    "por_concepto": true,
    "por_monto": true,
    "tolerancia": 150.00
  }
}
```

## Casos Especiales

### Programa TEMP → Real

✅ **Funciona correctamente**

1. Excel contiene `plan_estudios`
2. Sistema actualiza TEMP a programa real
3. Purge + Rebuild con el programa real
4. **Crea Cuota 0 si el programa real tiene inscripción**
5. Asigna pagos correctamente

### Sin Inscripción

Si el programa **NO** tiene inscripción configurada:

- ✅ NO se crea Cuota 0
- ✅ Cuotas 1..N se crean normalmente
- ✅ Sistema funciona como antes

### Múltiples Programas

Si un estudiante tiene varios programas:

- ✅ Cada programa puede tener su propia Cuota 0
- ✅ Asignación se hace al programa correcto
- ✅ Lógica de identificación de programa funciona como siempre

## Troubleshooting

### ❓ ¿Por qué no se creó Cuota 0?

**Verificar:**
```sql
-- 1. Verificar precio_programa
SELECT inscripcion FROM tb_precios_programa WHERE programa_id = ?;

-- 2. Verificar estudiante_programa
SELECT inscripcion FROM estudiante_programa WHERE id = ?;

-- 3. Verificar si se usó modo reemplazo
-- Debe estar activado: modoReemplazo = true
```

### ❓ ¿Por qué el pago no se asignó a Cuota 0?

**Revisar:**
1. ¿El concepto contiene "inscrip"?
2. ¿El monto está dentro de tolerancia?
3. ¿La Cuota 0 está en estado "pendiente"?

**Buscar en logs:**
```bash
grep "Cuota 0" storage/logs/laravel.log
```

### ❓ ¿Cómo cambiar la tolerancia?

**Ubicación:** `app/Imports/PaymentHistoryImport.php` línea ~817

```php
// Actual (30%)
$toleranciaIns = max(100, $cuotaInscripcion->monto * 0.30);

// Para cambiar a 40%
$toleranciaIns = max(100, $cuotaInscripcion->monto * 0.40);
```

## Configuración de Precio Programa

### Insertar Nuevo Precio

```sql
INSERT INTO tb_precios_programa (
    programa_id,
    inscripcion,
    cuota_mensual,
    meses
) VALUES (
    1,         -- ID del programa
    500.00,    -- Monto de inscripción
    800.00,    -- Cuota mensual
    12         -- Duración en meses
);
```

### Actualizar Precio Existente

```sql
UPDATE tb_precios_programa
SET inscripcion = 500.00
WHERE programa_id = 1;
```

## Consultas Útiles

### Ver Cuotas de un Estudiante (incluyendo Cuota 0)

```sql
SELECT 
    numero_cuota,
    monto,
    estado,
    fecha_vencimiento,
    paid_at
FROM cuotas_programa_estudiante
WHERE estudiante_programa_id = ?
ORDER BY numero_cuota;
```

### Ver Pagos de Inscripción

```sql
SELECT 
    k.id as kardex_id,
    k.numero_boleta,
    k.monto_pagado,
    k.fecha_pago,
    c.numero_cuota,
    c.monto as monto_cuota
FROM kardex_pagos k
JOIN cuotas_programa_estudiante c ON k.cuota_id = c.id
WHERE c.numero_cuota = 0
  AND k.estudiante_programa_id = ?;
```

### Ver Programas con Inscripción Configurada

```sql
SELECT 
    p.id,
    p.nombre_del_programa,
    pp.inscripcion,
    pp.cuota_mensual,
    pp.meses
FROM programas p
LEFT JOIN tb_precios_programa pp ON p.id = pp.programa_id
WHERE pp.inscripcion > 0;
```

## Mejores Prácticas

1. **Usar concepto descriptivo** en Excel:
   - ✅ "Inscripción", "Inscripción Inicial", "Matrícula"
   - ❌ "Pago 1", "Cuota", "Abono"

2. **Verificar precios antes de importar**:
   ```sql
   SELECT * FROM tb_precios_programa WHERE programa_id = ?;
   ```

3. **Usar modo reemplazo para migraciones**:
   - Garantiza malla limpia y correcta
   - Detecta inscripción automáticamente

4. **Revisar logs después de importar**:
   ```bash
   tail -f storage/logs/laravel.log | grep -i inscripcion
   ```

## Soporte y Referencias

- **Documentación completa:** `CUOTA_0_INSCRIPCION_IMPLEMENTATION.md`
- **Tolerancias:** `TOLERANCE_QUICK_REF.md`
- **Logging:** `PAYMENT_HISTORY_IMPORT_LOGGING_GUIDE.md`

## Changelog

### v1.0 (2024)
- ✅ Implementación inicial de Cuota 0
- ✅ Detección por concepto y monto
- ✅ Soporte de pagos parciales
- ✅ Integración con modo reemplazo
- ✅ Logs detallados

---

**¿Preguntas?** Consulta la documentación completa o revisa los logs del sistema.
