# Duplicate Bank Receipt Prevention System

## Overview

This implementation prevents duplicate bank receipt registrations by students using a comprehensive validation system based on normalized bank + receipt number combinations and file content hashing.

## Key Features

### 1. Global Bank Receipt Uniqueness
- **Rule**: No two payments can use the same (banco + numero_boleta) combination globally
- **Normalization**: 
  - `banco_norm` = UPPER(banco) with trimmed spaces
  - `numero_boleta_norm` = UPPER(numero_boleta) with only A-Z0-9 characters
- **Enforcement**: Unique database constraint on `(banco_norm, numero_boleta_norm)`

### 2. File Content Duplication Prevention
- **Rule**: Same student cannot upload the same file content multiple times
- **Method**: SHA-256 hash comparison of file contents
- **Enforcement**: Unique database constraint on `(estudiante_programa_id, file_sha256)`

### 3. Preflight Verification
- **Endpoint**: `GET /api/estudiante/pagos/boletas/verify?banco=X&numero_boleta=Y`
- **Purpose**: Allow frontend to check for duplicates before file upload
- **Response**: Different messages for own vs other student's duplicates

## API Endpoints

### 1. Preflight Verification
```
GET /api/estudiante/pagos/boletas/verify
Parameters: banco, numero_boleta
```

**Responses:**
- Available receipt: `{"exists": false, "message": "Esta boleta está disponible para uso."}`
- Own duplicate: `{"exists": true, "is_own": true, "message": "Esta boleta ya fue registrada por usted el 15/08/2025 (estado: aprobado)."}`
- Other student's: `{"exists": true, "is_own": false, "message": "Esta boleta ya fue utilizada por otro estudiante."}`

### 2. Payment Upload (Enhanced)
```
POST /api/estudiante/pagos/subir-recibo
Parameters: cuota_id, numero_boleta, banco, monto, comprobante
```

**New Validations:**
- Bank receipt uniqueness check
- File content duplication check
- Proper error messages for each scenario

## Database Changes

### New Columns in `kardex_pagos`
- `banco_norm` - Normalized bank name
- `numero_boleta_norm` - Normalized receipt number
- `file_sha256` - SHA-256 hash of uploaded file
- `fecha_aprobacion` - Approval timestamp
- `aprobado_por` - Who approved the payment

### New Indexes
- `unique_bank_receipt` on `(banco_norm, numero_boleta_norm)`
- `unique_file_per_student` on `(estudiante_programa_id, file_sha256)`

### Updated Estado Enum
Extended `estado_pago` to include: `pendiente_revision`, `en_revision`, `aprobado`, `rechazado`, `anulado`

## Normalization Rules

### Bank Name
```php
$banco_norm = strtoupper(trim($banco));
// "banco industrial" → "BANCO INDUSTRIAL"
```

### Receipt Number
```php
$numero_boleta_norm = preg_replace('/[^A-Z0-9]/', '', strtoupper($numeroBoleta));
// "ABC-123" → "ABC123"
// "xyz 456" → "XYZ456"
```

## File Handling

1. **Temporary Storage**: Files are first stored in `/temp` for hash calculation
2. **Hash Calculation**: SHA-256 hash computed from file contents
3. **Duplicate Check**: Hash compared against existing records for same student
4. **Final Storage**: File moved to `/recibos_pago` with unique filename
5. **Cleanup**: Temporary files always deleted (even on errors)

## Transaction Safety

All payment creation operations are wrapped in database transactions to ensure:
- Payment record creation
- Cuota status update
- Atomic rollback on any failure

## Error Messages

The system provides clear, user-friendly error messages:
- **Own duplicate**: "Esta boleta ya fue registrada por usted el {fecha} (estado: {estado})."
- **Other student's duplicate**: "Esta boleta ya fue utilizada por otro estudiante."
- **File duplicate**: "Este comprobante ya fue cargado previamente."

## Model Enhancements

### Automatic Normalization
The `KardexPago` model automatically normalizes `banco` and `numero_boleta` fields when saving using model events.

### New Scopes
- `pendientesRevision()` - Includes both 'pendiente_revision' and 'en_revision'
- `rechazados()` - Only 'rechazado' status
- `anulados()` - Only 'anulado' status

### Helper Methods
- `receiptExists($banco, $numeroBoleta)` - Check for duplicate receipts
- `fileHashExists($estudianteProgramaId, $fileHash)` - Check for duplicate files
- `calculateFileHash($filePath)` - Calculate SHA-256 hash of file