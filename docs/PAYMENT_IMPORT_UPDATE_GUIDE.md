# Payment History Import Logic Update - Implementation Guide

## Overview

This document describes the changes made to the `PaymentHistoryImport` class to support a new Excel structure and implement quota auto-generation based on the `estudiante_programa` table.

## Changes Summary

### 1. New Excel Structure Support

The import now accepts additional columns:
- `tipo_pago` - Type of payment (MENSUAL, ESPECIAL, INSCRIPCION, etc.)
- `mes_pago` - Month of payment
- `ano` - Year
- `concepto` - Payment concept/description
- `estatus` - Student status

**Old required columns:**
```php
['carnet', 'nombre_estudiante', 'numero_boleta', 'monto', 'fecha_pago', 'mensualidad_aprobada']
```

**New required columns:**
```php
['carnet', 'nombre_estudiante', 'numero_boleta', 'monto', 'fecha_pago']
// mensualidad_aprobada is now optional
```

### 2. Payment Type Logic (`tipo_pago`)

#### New Method: `esPagoMensual()`

Determines if a payment should be assigned to a quota based on its type:

**Monthly Payment Types** (assigned to quotas):
- MENSUAL
- MENSUALIDAD
- CUOTA
- CUOTA MENSUAL
- (any unrecognized type defaults to monthly for backwards compatibility)

**Special Payment Types** (NOT assigned to quotas):
- ESPECIAL
- INSCRIPCION / INSCRIPCIÓN
- RECARGO
- MORA
- EXTRAORDINARIO

**Usage:**
```php
$tipoPago = 'MENSUAL';
if ($this->esPagoMensual($tipoPago)) {
    // Assign to quota
} else {
    // Skip quota assignment
}
```

### 3. Quota Generation Based on `duracion_meses`

#### Updated Method: `generarCuotasSiFaltan()`

**Old behavior:**
- Generated all quotas from scratch if none existed

**New behavior:**
- Checks `estudiante_programa.duracion_meses` to determine expected quotas
- Counts existing quotas
- Only creates missing quotas (difference between expected and existing)
- Uses `estudiante_programa.cuota_mensual` for quota amounts

**Example:**
```php
// estudiante_programa data
duracion_meses = 12
cuota_mensual = 1500.00
fecha_inicio = '2024-01-01'

// Existing quotas in database
cuotas_existentes = 8

// Action taken
cuotas_faltantes = 12 - 8 = 4
// Creates quotas #9, #10, #11, #12 with amount Q1,500.00 each
```

### 4. Enhanced Payment Processing

#### Changes in `procesarPagoIndividual()`

1. **Extract new fields:**
```php
$tipoPago = trim(strtoupper((string)($row['tipo_pago'] ?? 'MENSUAL')));
$ano = trim((string)($row['ano'] ?? ''));
$estatus = trim((string)($row['estatus'] ?? ''));
```

2. **Conditional quota assignment:**
```php
$esMenual = $this->esPagoMensual($tipoPago);

if ($esMenual) {
    // Search and assign quota
    $cuota = $this->buscarCuotaFlexible(...);
} else {
    // Skip quota assignment
    $cuota = null;
}
```

3. **Enhanced observaciones:**
```php
$observaciones = sprintf(
    "%s | Estudiante: %s | Mes: %s | Tipo: %s | Migración fila %d | Programa: %s",
    $concepto,
    $nombreEstudiante,
    $mesPago,
    $tipoPago,  // ← NEW
    $numeroFila,
    $programaAsignado->nombre_programa ?? 'N/A'
);
```

## Usage Examples

### Example 1: Excel with New Structure

```excel
carnet        | nombre_estudiante | plan_estudios | estatus | numero_boleta | monto   | fecha_pago | banco | concepto       | tipo_pago   | mes_pago | año
ASM2020103   | Juan Pérez       | MMK          | Activo  | BOL-001      | 1500.00 | 2024-01-15 | BI    | Cuota enero   | MENSUAL     | Enero    | 2024
ASM2020103   | Juan Pérez       | MMK          | Activo  | BOL-002      | 1500.00 | 2024-02-15 | BI    | Cuota febrero | MENSUAL     | Febrero  | 2024
ASM2020103   | Juan Pérez       | MMK          | Activo  | BOL-003      | 500.00  | 2024-03-15 | BI    | Recargo mora  | ESPECIAL    | Marzo    | 2024
```

**Result:**
- BOL-001: Creates Kardex + Assigns to Quota #1 ✅
- BOL-002: Creates Kardex + Assigns to Quota #2 ✅
- BOL-003: Creates Kardex + NO quota assignment (special payment) ✅

### Example 2: Auto-generate Missing Quotas

**Scenario:**
- Student has `duracion_meses = 12` in `estudiante_programa`
- Only 5 quotas exist in database
- Import processes 3 monthly payments

**Result:**
1. System detects 12 - 5 = 7 missing quotas
2. Creates quotas #6 through #12
3. Assigns the 3 monthly payments to quotas #6, #7, #8
4. Quotas #9-#12 remain pending

## Testing

Run the validation scripts to verify the logic:

```bash
# Test payment type logic
php /tmp/test_payment_import.php

# Test comprehensive logic
php /tmp/test_payment_logic.php

# Check syntax
php -l app/Imports/PaymentHistoryImport.php
```

## Migration Path

### For Old Excel Files (without tipo_pago)
- Works seamlessly
- All payments default to "MENSUAL" (monthly)
- Assigned to quotas as before

### For New Excel Files (with tipo_pago)
- Reads tipo_pago column
- Skips quota assignment for special payments
- Creates missing quotas based on duracion_meses

## Benefits

1. **Flexible Payment Types**: Distinguish between monthly and special payments
2. **Smart Quota Generation**: Only creates missing quotas, not duplicates
3. **Better Tracking**: Observaciones include payment type
4. **Backwards Compatible**: Old Excel files still work
5. **Data Integrity**: Uses authoritative data from estudiante_programa table

## Notes

- The `mensualidad_aprobada` column is now optional but still used when present
- Special payments (ESPECIAL, INSCRIPCION, etc.) still create Kardex entries, just without quota assignment
- The system uses `duracion_meses` from `estudiante_programa` as the source of truth for expected quotas
- If `duracion_meses` or `cuota_mensual` are 0 or missing, the system falls back to `precio_programa` table
