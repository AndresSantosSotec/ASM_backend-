# Bank Statement Import/Export Implementation

This document describes the implementation of the bank statement import/export system for reconciliation records.

## Components Implemented

### 1. Database Migration
- **File**: `database/migrations/2025_09_11_081432_add_normalized_fields_to_reconciliation_records_table.php`
- **Purpose**: Adds normalized fields to reconciliation_records table
- **Fields Added**:
  - `bank_normalized` (string, nullable)
  - `reference_normalized` (string, nullable) 
  - `fingerprint` (string, nullable, indexed)

### 2. Model Updates
- **File**: `app/Models/ReconciliationRecord.php`
- **Changes**: Added new fields to `$fillable` array

### 3. Import Class
- **File**: `app/Imports/BankStatementImport.php`
- **Features**:
  - Normalizes bank names to canonical forms
  - Normalizes receipt numbers (removes special characters)
  - Parses amounts in various formats (Q1,234.56, 1.234,56, etc.)
  - Parses dates in multiple formats (dd/mm/yyyy, yyyy-mm-dd, Excel serials)
  - Maps various header names to canonical fields
  - Creates fingerprints for deduplication
  - Supports upsert operations based on fingerprint

### 4. Export Classes
- **File**: `app/Exports/ReconciliationTemplateExport.php`
  - Provides template with correct headers and sample data
- **File**: `app/Exports/ReconciliationRecordsExport.php`
  - Exports filtered reconciliation records
  - Supports filtering by date range, bank, and status

### 5. Controller Methods
- **File**: `app/Http/Controllers/Api/ReconciliationController.php`
- **New Methods**:
  - `import()` - Handles file upload and import
  - `downloadTemplate()` - Downloads import template
  - `export()` - Exports filtered records

### 6. API Routes
- **File**: `routes/api.php`
- **New Routes**:
  - `POST /api/conciliacion/import`
  - `GET /api/conciliacion/template`
  - `GET /api/conciliacion/export`

## Bank Normalization Mapping

The system normalizes bank names to canonical forms:

```php
'BANCO INDUSTRIAL' => ['BI','BANCO INDUSTRIAL','INDUSTRIAL']
'BANRURAL'         => ['BANRURAL','BAN RURAL','RURAL']
'BAM'              => ['BAM','BANCO AGROMERCANTIL']
'G&T CONTINENTAL'  => ['G&T','G Y T','GYT','G&T CONTINENTAL']
'PROMERICA'        => ['PROMERICA']
```

## Header Mapping

The import system automatically maps various header names:

- Bank: "banco", "BANCO"
- Reference: "referencia", "boleta", "volante", "voucher"
- Amount: "monto", "importe"
- Date: "fecha"
- Authorization: "autoriz"

## Amount Parsing

Supports multiple amount formats:
- `1234.56` - Standard decimal
- `1,234.56` - US format with comma thousands separator
- `1.234,56` - European format with dot thousands separator
- `Q1,234.56` - With Quetzal prefix
- `Q 1,234.56` - With spaces

## Date Parsing

Supports multiple date formats:
- `yyyy-mm-dd` - ISO format
- `dd/mm/yyyy` - Common format
- Excel serial numbers
- General Carbon parsing as fallback

## Fingerprint System

Each record gets a unique fingerprint for deduplication:
```
{bank_normalized}|{reference_normalized}|{amount}|{date}
```

Example: `BANCO INDUSTRIAL|BI123456|750.00|2025-03-10`

## Integration with Existing System

The implementation is fully compatible with the existing `kardexNoConciliados` endpoint, which compares KardexPago records with ReconciliationRecord entries using the same normalization and fingerprint logic.

## Usage Examples

### Import Bank Statement
```bash
curl -X POST /api/conciliacion/import \
  -F "file=@bank_statement.xlsx"
```

### Download Template
```bash
curl -O /api/conciliacion/template
```

### Export Records
```bash
curl "/api/conciliacion/export?from=2025-01-01&to=2025-12-31&bank=INDUSTRIAL&status=uploaded"
```

## Testing

The implementation includes comprehensive tests that validate:
- Bank name normalization consistency
- Receipt number normalization
- Amount parsing in various formats
- Date parsing capabilities
- Fingerprint generation
- Integration with existing controller methods

All tests pass and the system is ready for production use.