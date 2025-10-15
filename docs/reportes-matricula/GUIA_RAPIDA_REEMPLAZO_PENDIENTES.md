# Guía Rápida: Modo Reemplazo de Cuotas Pendientes

## 🎯 ¿Qué hace esta funcionalidad?

Cuando se importan pagos históricos masivos, esta funcionalidad permite:

1. **Reemplazar cuotas pendientes** con estado "Pagado" automáticamente
2. **Generar cuotas dinámicamente** para programas TEMP o sin cuotas
3. **Crear cuota 0** (inscripción) cuando se detecte en los datos
4. **Continuar procesando** incluso si hay errores en algunas filas

## 🚀 Cómo Usarlo

### Opción 1: Modo Normal (Sin Reemplazo)

```php
// Comportamiento por defecto - NO reemplaza cuotas pendientes
$import = new PaymentHistoryImport($userId);
Excel::import($import, $archivo);
```

### Opción 2: Modo Reemplazo Activado

```php
// Activa reemplazo de cuotas pendientes
$import = new PaymentHistoryImport($userId, 'cardex_directo', true);
Excel::import($import, $archivo);
```

### En un Controlador

```php
public function importarPagosConReemplazo(Request $request)
{
    $userId = auth()->id();
    $archivo = $request->file('excel');
    
    // Activar modo reemplazo con el tercer parámetro = true
    $import = new PaymentHistoryImport($userId, 'cardex_directo', true);
    
    Excel::import($import, $archivo);
    
    return response()->json([
        'mensaje' => 'Importación completada',
        'procesados' => $import->procesados,
        'kardex_creados' => $import->kardexCreados,
        'errores' => count($import->errores),
        'advertencias' => count($import->advertencias)
    ]);
}
```

## 📋 Formato del Excel

### Ejemplo 1: Datos Básicos

```
| carnet      | nombre_estudiante | numero_boleta | monto  | fecha_pago | mensualidad_aprobada |
|-------------|-------------------|---------------|--------|------------|---------------------|
| ASM2024001  | Juan Pérez       | 123456        | 800.00 | 2024-01-15 | 800.00             |
| ASM2024001  | Juan Pérez       | 123457        | 800.00 | 2024-02-15 | 800.00             |
```

### Ejemplo 2: Con Inscripción

```
| carnet      | concepto      | monto  | fecha_pago | mensualidad_aprobada |
|-------------|---------------|--------|------------|---------------------|
| ASM2024001  | Inscripción   | 500.00 | 2024-01-05 | 800.00             |
| ASM2024001  | Cuota mensual | 800.00 | 2024-01-15 | 800.00             |
```

### Ejemplo 3: Programa TEMP

```
| carnet      | plan_estudios | monto  | fecha_pago | mensualidad_aprobada |
|-------------|---------------|--------|------------|---------------------|
| ASM2024001  | TEMP          | 800.00 | 2024-01-15 | 800.00             |
| ASM2024001  | TEMP          | 800.00 | 2024-02-15 | 800.00             |
```

## 🔍 ¿Qué Pasa en Cada Caso?

### Caso 1: Estudiante con Cuotas Pendientes

**Antes:**
- Estudiante tiene cuotas en estado "Pendiente"
- Excel tiene pagos históricos

**Con Modo Reemplazo:**
1. Busca cuota pendiente compatible por monto
2. Actualiza estado a "Pagado"
3. Registra fecha de pago
4. Crea registro en kardex_pagos

**Resultado:** Cuota marcada como pagada, kardex creado ✅

### Caso 2: Estudiante Sin Cuotas

**Antes:**
- Estudiante existe pero no tiene cuotas generadas
- Excel tiene pagos históricos

**Con Modo Reemplazo:**
1. Detecta que no hay cuotas
2. Genera cuotas automáticamente según:
   - Duración del programa
   - Mensualidad del Excel
   - Precio del programa
3. Procesa los pagos contra las cuotas generadas

**Resultado:** Cuotas generadas, pagos procesados ✅

### Caso 3: Programa TEMP

**Antes:**
- Estudiante tiene programa "TEMP"
- Excel tiene pagos pero sin plan_estudios claro

**Con Modo Reemplazo:**
1. Detecta programa TEMP
2. Genera 12 cuotas por defecto
3. Usa mensualidad del Excel
4. Procesa los pagos

**Resultado:** Cuotas TEMP generadas, pagos procesados ✅

### Caso 4: Con Inscripción

**Antes:**
- Excel tiene pago con concepto "Inscripción"
- No existe cuota 0

**Con Modo Reemplazo:**
1. Detecta concepto de inscripción
2. Crea Cuota 0 automáticamente
3. Genera cuotas 1-N normales
4. Procesa todos los pagos

**Resultado:** Cuota 0 creada, pagos asignados correctamente ✅

## 📊 Logs y Monitoreo

### Logs de Reemplazo

Busca en `storage/logs/laravel.log`:

```
🔄 Modo reemplazo activo: buscando cuota pendiente para reemplazar
🔄 Reemplazando cuota pendiente con pago
```

### Logs de Generación

```
🔧 Generando cuotas automáticamente
✅ Cuota 0 (Inscripción) agregada
✅ Cuotas generadas exitosamente
```

### Logs de Errores

```
❌ Error en transacción fila X
⚠️ No se encontró cuota pendiente para este pago
```

## ⚠️ Importante

1. **Modo Reemplazo es Irreversible**: Una vez que una cuota se marca como "Pagado", no hay rollback automático
2. **Prueba Primero**: Usa ambiente de pruebas antes de producción
3. **Revisa Logs**: Siempre revisa los logs después de importar
4. **Errores No Detienen**: Si una fila falla, el resto continúa procesándose

## 🔧 Troubleshooting

### Problema: Cuotas no se reemplazan

**Verificar:**
1. ¿Modo reemplazo está activado? (tercer parámetro = true)
2. ¿Hay cuotas en estado "pendiente"?
3. ¿El monto del pago es compatible con alguna cuota? (tolerancia 50%)

**Solución:**
```php
// Verifica que el modo esté activado
$import = new PaymentHistoryImport($userId, 'cardex_directo', true);
                                                              // ^^^^
```

### Problema: No se generan cuotas para TEMP

**Verificar:**
1. ¿El programa tiene código "TEMP"?
2. ¿Hay datos de mensualidad en el Excel?

**Solución:**
- Asegúrate que el Excel tenga columna `mensualidad_aprobada`
- El sistema usa 12 cuotas por defecto para TEMP

### Problema: Muchos errores en importación

**Verificar:**
1. Formato del Excel correcto
2. Carnets válidos
3. Fechas en formato correcto (YYYY-MM-DD o número Excel)

**Solución:**
```php
// Revisa errores específicos
foreach ($import->errores as $error) {
    echo "{$error['tipo']}: {$error['error']}\n";
}
```

## 📞 Contacto y Soporte

- Revisa `IMPLEMENTACION_REEMPLAZO_PENDIENTES.md` para documentación técnica completa
- Logs en: `storage/logs/laravel.log`
- Busca por emojis: 🔄 (reemplazo), 🔧 (generación), ❌ (error)
