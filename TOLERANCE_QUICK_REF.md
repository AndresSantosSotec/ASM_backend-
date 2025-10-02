# Quick Reference: Payment Import Tolerance Settings

## Current Tolerance Levels (Maximum for Historical Imports)

### Priority 1: Mensualidad Aprobada
- **Tolerance**: 50% or minimum Q100
- **Matches**: Payment amount vs approved monthly amount
- **Use case**: Standard monthly payments

### Priority 2: Monto de Pago
- **Tolerance**: 50% or minimum Q100
- **Matches**: Payment amount vs quota amount
- **Use case**: Any payment amount variations

### Priority 3: Partial Payment
- **Threshold**: 30% minimum
- **Detects**: Payments covering 30-99% of quota
- **Warning**: PAGO_PARCIAL

### Priority 4: Extreme Tolerance
- **Tolerance**: 100%
- **Matches**: Any amount within double the quota
- **Warning**: DIFERENCIA_MONTO_EXTREMA

### Priority 5: Forced Assignment
- **Tolerance**: None (assigns any pending quota)
- **Use case**: Last resort to preserve payment data
- **Warning**: CUOTA_FORZADA

## Program Pricing Fallback

When no quotas exist:
- Validates against `tb_precios_programa.cuota_mensual`
- Validates against `tb_precios_programa.inscripcion`
- Uses 50% tolerance for validation
- Logs validation results

## Import Success Expected Rates

| Priority | Expected % | Description |
|----------|-----------|-------------|
| Priority 1 | 40-50% | Standard monthly payments |
| Priority 2 | 10-20% | Amount variations |
| Priority 3 | 10-15% | Partial payments |
| Priority 4 | 5-10% | Large differences |
| Priority 5 | 5% | Forced assignments |
| **Total** | **90-95%** | Overall success rate |

## Common Warning Types

1. **PAGO_PARCIAL**: Payment < quota (but ≥30%)
2. **DIFERENCIA_MONTO_EXTREMA**: Large amount difference (Priority 4)
3. **CUOTA_FORZADA**: No amount validation (Priority 5)
4. **SIN_CUOTA**: No quota found at all (kardex only)

## Quick Troubleshooting

### High number of SIN_CUOTA warnings?
→ Check if quotas exist in `cuotas_programa_estudiante`
→ Verify program pricing in `tb_precios_programa`

### High number of CUOTA_FORZADA?
→ Review tolerance levels
→ Check if payment amounts are reasonable
→ Consider creating missing quotas

### Import still failing?
→ Check logs: `storage/logs/laravel.log`
→ Verify student exists in `prospectos`
→ Verify program enrollment in `estudiante_programa`

## Key Log Messages

```
✅ Cuota encontrada por mensualidad aprobada (Priority 1)
✅ Cuota encontrada por monto de pago (Priority 2)
⚠️ PAGO PARCIAL DETECTADO (Priority 3)
⚠️ Cuota encontrada con tolerancia extrema (Priority 4)
⚠️ Usando primera cuota pendiente sin validación (Priority 5)
💰 Precio de programa encontrado para validación
```

## Related Files

- `TOLERANCE_IMPROVEMENTS.md` - Complete documentation
- `QUOTA_MATCHING_FIX.md` - Previous tolerance changes
- `app/Imports/PaymentHistoryImport.php` - Implementation

## Quick Commands

```bash
# View tolerance-related logs
grep "tolerancia" storage/logs/laravel.log | tail -20

# Count warning types
grep "PAGO_PARCIAL\|DIFERENCIA_MONTO_EXTREMA\|CUOTA_FORZADA\|SIN_CUOTA" storage/logs/laravel.log | sort | uniq -c

# Check import success rate
grep "RESUMEN FINAL" storage/logs/laravel.log | tail -1
```
