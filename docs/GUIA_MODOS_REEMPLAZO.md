# Guía de Uso: Modos de Reemplazo de Cuotas

## Resumen

Se han implementado dos modos de reemplazo en `PaymentHistoryImport` para evitar duplicados de cuotas:

1. **Modo Reemplazo de Pendientes** (`modoReemplazoPendientes`): Actualiza cuotas pendientes a pagadas
2. **Modo Reemplazo Total** (`modoReemplazo`): Purga y reconstruye todas las cuotas

## Uso

### Modo Normal (Sin Reemplazo)

```php
// Comportamiento actual - no modifica cuotas existentes
$import = new PaymentHistoryImport($uploaderId);
Excel::import($import, $file);
```

### Modo Reemplazo de Pendientes

Ideal para actualizar pagos en cuotas que ya existen pero están pendientes:

```php
$import = new PaymentHistoryImport(
    $uploaderId, 
    'cardex_directo',
    true  // modoReemplazoPendientes activado
);
Excel::import($import, $file);
```

**¿Qué hace?**
- Busca cuotas pendientes que coincidan con el pago
- Actualiza la cuota a estado "pagado" con la fecha del pago
- Evita crear kardex duplicados para pagos ya procesados
- Usa tolerancia del 50% para coincidencias flexibles

### Modo Reemplazo Total

Ideal para reimportar datos históricos desde cero:

```php
$import = new PaymentHistoryImport(
    $uploaderId,
    'cardex_directo', 
    false, // modoReemplazoPendientes desactivado
    true   // modoReemplazo activado
);
Excel::import($import, $file);
```

**¿Qué hace?**
1. **PURGA** todos los datos existentes del estudiante:
   - Kardex de pagos
   - Registros de conciliación
   - Cuotas del programa
2. **RECONSTRUYE** las cuotas según configuración del programa:
   - Usa duración del programa
   - Aplica cuota mensual configurada
   - Crea cuota 0 (inscripción) si aplica
3. **PROCESA** los pagos normalmente sobre las nuevas cuotas

### Ambos Modos Simultáneamente

```php
$import = new PaymentHistoryImport(
    $uploaderId,
    'cardex_directo',
    true,  // modoReemplazoPendientes
    true   // modoReemplazo
);
Excel::import($import, $file);
```

**Flujo:**
1. Primero ejecuta el reemplazo total (purge + rebuild)
2. Luego procesa pagos con reemplazo de pendientes activo

## Prevención de Duplicados

### En generarCuotasSiFaltan()

El método ahora verifica si ya existen cuotas antes de generar:

```php
$cuotasExistentes = DB::table('cuotas_programa_estudiante')
    ->where('estudiante_programa_id', $estudianteProgramaId)
    ->count();

if ($cuotasExistentes > 0) {
    // No genera cuotas duplicadas
    return false;
}
```

### En reemplazarCuotaPendiente()

Solo actualiza cuotas en estado "pendiente", nunca duplica cuotas pagadas.

## Ejemplos de Uso

### Ejemplo 1: Primera importación de datos históricos

```php
// Usar modo reemplazo total para limpiar y empezar desde cero
$import = new PaymentHistoryImport($userId, 'cardex_directo', false, true);
Excel::import($import, 'pagos_historicos_2023.xlsx');
```

### Ejemplo 2: Actualizar pagos recientes sobre estructura existente

```php
// Usar modo reemplazo de pendientes para actualizar pagos sin perder estructura
$import = new PaymentHistoryImport($userId, 'cardex_directo', true, false);
Excel::import($import, 'pagos_recientes.xlsx');
```

### Ejemplo 3: Corrección de datos incorrectos

```php
// Purgar todo y reconstruir correctamente
$import = new PaymentHistoryImport($userId, 'cardex_directo', false, true);
Excel::import($import, 'datos_corregidos.xlsx');
```

## Logs Generados

### Modo Reemplazo Total

```
🔄 MODO REEMPLAZO ACTIVO: Se eliminará y reconstruirá todo para cada estudiante
🔄 [Reemplazo] Procesando carnet ASM2020103
🧹 [Replace] PURGE EP 162 (Licenciatura en Contabilidad)
   • conciliaciones eliminadas: 12
   • kardex eliminados: 12
   • cuotas eliminadas: 40
🔧 [Replace] Rebuild cuotas
✅ [Replace] Malla reconstruida (incluye cuota 0 si aplica)
✅ [Reemplazo] Carnet ASM2020103 listo para importación
```

### Modo Reemplazo de Pendientes

```
🔄 Modo reemplazo activo: buscando cuota pendiente para reemplazar
✅ Cuota pendiente encontrada por mensualidad aprobada
🔄 Reemplazando cuota pendiente con pago
   cuota_id: 1234
   estado_anterior: pendiente
   estado_nuevo: pagado
   fecha_pago: 2023-06-15
```

## Consideraciones Importantes

### ⚠️ Modo Reemplazo Total es Destructivo

- **Elimina TODOS** los datos existentes del estudiante
- No hay forma de recuperar datos eliminados
- Usar solo cuando sea necesario un reinicio completo
- Hacer backup antes de usar

### ✅ Modo Reemplazo de Pendientes es Conservador

- Solo modifica cuotas pendientes
- No elimina datos históricos
- Mantiene estructura existente
- Seguro para actualizaciones incrementales

### 🔍 Verificación Automática de Duplicados

Ambos modos incluyen verificaciones para evitar:
- Duplicación de cuotas
- Duplicación de kardex (por boleta y fingerprint)
- Duplicación de conciliaciones

## Troubleshooting

### Problema: Cuotas siguen duplicándose

**Verificar:**
1. ¿Se está usando el modo correcto?
2. ¿El código de programa es "TEMP"? (genera cuotas dinámicamente)
3. ¿Hay múltiples importaciones simultáneas?

**Solución:**
- Usar `modoReemplazo = true` para limpiar duplicados
- Verificar que no hay procesos concurrentes

### Problema: Cuotas no se reemplazan

**Verificar:**
1. ¿`modoReemplazoPendientes = true`?
2. ¿Las cuotas están en estado "pendiente"?
3. ¿El monto coincide con tolerancia del 50%?

**Solución:**
- Revisar logs para ver qué cuotas se encontraron
- Ajustar tolerancia si es necesario
- Verificar que las cuotas no estén ya pagadas

### Problema: Error al purgar datos

**Verificar:**
1. Permisos de base de datos
2. Restricciones de foreign keys
3. Logs de error

**Solución:**
- Verificar que el usuario tiene permisos DELETE
- Revisar configuración de base de datos
- Consultar logs: `storage/logs/laravel.log`

## Testing

Para validar que la implementación funciona correctamente:

```bash
# Verificar sintaxis
php -l app/Imports/PaymentHistoryImport.php
php -l app/Services/PaymentReplaceService.php

# Ejecutar validación
php /tmp/validate_replacement_modes.php
```

## Documentación Relacionada

- `IMPLEMENTACION_REEMPLAZO_PENDIENTES.md` - Detalles técnicos
- `CUOTA_0_INSCRIPCION_IMPLEMENTATION.md` - Implementación de cuota 0
- `QUICK_REFERENCE_CUOTAS_FIX.md` - Referencia rápida de cuotas

## Soporte

Si encuentras problemas:

1. Revisar logs en `storage/logs/laravel.log`
2. Buscar mensajes que comiencen con `🔄`, `🧹`, `🔧`
3. Verificar estado de cuotas en base de datos
4. Compartir logs y contexto para análisis
