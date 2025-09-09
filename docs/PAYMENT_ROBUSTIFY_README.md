# Payment Receipt Registration - Security Enhancements

## Overview
This document describes the security and reliability enhancements implemented for the payment receipt registration system to prevent duplicates and fraud.

## Key Problems Solved

### 1. Duplicate Boleta Prevention
**Before**: Students could submit the same boleta number multiple times with slight formatting differences:
- `BI-001 234` and `BI001234` were treated as different boletas
- Same boleta could be used across multiple programs

**After**: Advanced normalization and uniqueness validation:
- All format variations are normalized to `BI001234`
- Unique database constraints prevent exact duplicates
- Soft duplicate warnings for suspicious patterns

### 2. File Reuse Prevention
**Before**: Same PDF/JPG receipt could be uploaded multiple times for different payments

**After**: SHA256 hash validation prevents file reuse:
- Each file gets a unique hash fingerprint
- Database constraint prevents reuse of same file hash per student

### 3. Business Rule Enforcement
**Before**: No validation of payment amounts or timing

**After**: Comprehensive business logic:
- Early payment blocking (configurable)
- Amount validation with late fee calculation
- Auto-approval only for exact amounts
- Manual review queue for discrepancies

### 4. Security & Reliability
**Before**: No protection against replay attacks or system abuse

**After**: Multiple security layers:
- Rate limiting (6 requests per minute)
- Idempotency key support for duplicate request prevention
- Comprehensive audit logging
- Secure file naming and storage

## API Changes

### Request Format
```
POST /api/estudiante/pagos/subir-recibo
Content-Type: multipart/form-data
Idempotency-Key: [optional UUID]

Fields:
- cuota_id: integer (required)
- numero_boleta: string (required, max 100 chars)
- banco: string (required, max 100 chars)
- monto: decimal (required, min 0)
- comprobante: file (required, PDF/JPG/JPEG/PNG, max 5MB)
```

### Response Formats

#### Success (Auto-approved)
```json
{
    "success": true,
    "message": "Pago procesado exitosamente. Su cuota ha sido marcada como pagada.",
    "pago_id": 123,
    "estado_cuota": "pagado",
    "estado_pago": "aprobado",
    "fecha_procesamiento": "2025-09-09 17:30:00",
    "monto_esperado": 1000.00,
    "monto_recibido": 1000.00
}
```

#### Success (Sent to Review)
```json
{
    "success": true,
    "message": "Pago recibido y enviado a revisión debido a diferencias en el monto.",
    "pago_id": 124,
    "estado_cuota": "pendiente",
    "estado_pago": "en_revision",
    "fecha_procesamiento": "2025-09-09 17:31:00",
    "monto_esperado": 1000.00,
    "monto_recibido": 950.00
}
```

#### Validation Errors (422)
```json
{
    "success": false,
    "field_errors": {
        "numero_boleta": ["Esta boleta ya ha sido registrada anteriormente."],
        "comprobante": ["Este archivo ya ha sido utilizado anteriormente."]
    }
}
```

## Database Changes

### New Columns in `kardex_pagos`
```sql
numero_boleta_norm VARCHAR(120) -- Normalized boleta number
banco_norm VARCHAR(80)          -- Normalized bank name
file_sha256 CHAR(64)            -- File hash for duplicate detection
fecha_aprobacion TIMESTAMP      -- When payment was approved
aprobado_por VARCHAR(100)       -- Who approved (system_automatico/user)
ip_address VARCHAR(45)          -- Client IP for audit
user_agent TEXT                 -- Client user agent for audit
```

### New Table: `payment_requests`
```sql
CREATE TABLE payment_requests (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    idempotency_key VARCHAR(36) UNIQUE,
    user_id BIGINT,
    request_payload JSON,
    response_payload JSON,
    response_status INTEGER,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Unique Constraints
```sql
-- Prevent same boleta for same student/program and bank
UNIQUE(estudiante_programa_id, banco_norm, numero_boleta_norm)

-- Prevent file reuse for same student/program  
UNIQUE(estudiante_programa_id, file_sha256)
```

## Configuration

### Environment Variables
```env
# Payment Policy Configuration
PAYMENT_ALLOW_EARLY=true                    # Allow payments before due date
PAYMENT_AMOUNT_TOLERANCE=0.01               # Max difference for auto-approval
PAYMENT_AUTO_APPROVE_EXACT=true             # Auto-approve exact amounts
PAYMENT_RATE_LIMIT=6                        # Requests per minute
PAYMENT_MAX_FILE_SIZE=5120                  # Max file size in KB
PAYMENT_IDEMPOTENCY_EXPIRATION=1440         # Idempotency key TTL in minutes
```

## Usage Examples

### 1. Standard Payment (Auto-approved)
```bash
curl -X POST /api/estudiante/pagos/subir-recibo \
  -H "Authorization: Bearer [token]" \
  -H "Idempotency-Key: 123e4567-e89b-12d3-a456-426614174000" \
  -F "cuota_id=123" \
  -F "numero_boleta=BI001234" \
  -F "banco=Banco Industrial" \
  -F "monto=1000.00" \
  -F "comprobante=@receipt.pdf"
```

### 2. Duplicate Detection
If the same request is made again:
```json
{
    "success": false,
    "field_errors": {
        "numero_boleta": ["Esta boleta ya ha sido registrada anteriormente."]
    }
}
```

### 3. Idempotency Protection
If the same `Idempotency-Key` is used:
- Returns the exact same response as the first request
- No duplicate payment is created

## Monitoring & Observability

### Log Events
All payment attempts are logged with:
- User ID and carnet
- Payment details (amount, boleta, bank)
- IP address and user agent
- Validation results
- Processing time

### Recommended Metrics
- `payments.duplicate_boleta_detected`
- `payments.file_hash_duplicate`
- `payments.en_revision`
- `payments.auto_approved`
- `payments.rate_limited`

## Migration Guide

### 1. Run Database Migrations
```bash
php artisan migrate
```

### 2. Backfill Existing Data
The migration automatically normalizes existing boleta and bank data.

### 3. Update Frontend
- Add support for new response fields
- Handle new validation error messages
- Optionally implement Idempotency-Key generation

### 4. Configure Policies
Set environment variables according to business requirements.

## Testing

### Key Test Scenarios
1. **Normalization**: Verify format variations are treated as same boleta
2. **Duplicates**: Test exact and soft duplicate detection
3. **File Reuse**: Verify same file hash is rejected
4. **Amount Validation**: Test auto-approval vs review logic
5. **Rate Limiting**: Verify throttling works
6. **Idempotency**: Test duplicate request handling

### Example Test Cases
```php
// Test boleta normalization
$this->assertEquals('BI001234', Boletas::normalize('bi- 001 234'));

// Test duplicate detection
// ... submit payment with boleta BI001234
// ... try to submit again with bi-001-234
// ... should be rejected as duplicate
```

## Security Considerations

### 1. File Upload Security
- Restricted to PDF, JPG, JPEG, PNG only
- Maximum 5MB file size
- Secure random file naming
- SHA256 hash validation

### 2. Rate Limiting
- 6 requests per minute per user
- Prevents rapid-fire duplicate submissions
- Helps detect automated attacks

### 3. Audit Trail
- All attempts logged with IP and user agent
- Payment approval workflow tracked
- Database changes include audit metadata

### 4. Data Integrity
- Database constraints prevent duplicates
- Transaction handling ensures consistency
- Normalization prevents format-based bypasses

## Troubleshooting

### Common Issues

1. **"Esta boleta ya ha sido registrada"**
   - Check if boleta was previously used (exact match)
   - Verify student and program context

2. **"Este archivo ya ha sido utilizado"**
   - Student trying to reuse same PDF/JPG file
   - File content is identical to previous upload

3. **"Pago enviado a revisión"**
   - Amount differs from expected (base + late fees)
   - Manual review required by administrator

4. **Rate limit exceeded**
   - User making too many requests too quickly
   - Wait before retrying or investigate potential abuse

### Debug Steps
1. Check application logs for detailed error context
2. Verify database constraints and normalized values
3. Test boleta normalization manually
4. Check file hash calculation
5. Verify rate limiting configuration