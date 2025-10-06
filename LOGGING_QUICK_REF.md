# Quick Reference: Logging Optimization

## 🚀 Quick Start

### For Production (Recommended)
```bash
# .env file
IMPORT_VERBOSE=false
```
or simply **omit the variable** (defaults to false)

### For Development/Debugging
```bash
# .env file
IMPORT_VERBOSE=true
```

## 📊 What Gets Logged

### Production Mode (IMPORT_VERBOSE=false)
```
✅ Start message
✅ Errors (Log::error)
✅ Warnings (Log::warning)  
✅ Final summary with totals
❌ Row-by-row details
❌ Student lookups
❌ Payment matching steps
```

### Development Mode (IMPORT_VERBOSE=true)
```
✅ Everything above PLUS:
✅ Every row processed
✅ Student lookups
✅ Program identification
✅ Payment matching logic
✅ Database operations
✅ Cache usage
```

## 🔧 Configuration

### 1. Set Environment Variable
```bash
# .env
IMPORT_VERBOSE=false
```

### 2. Clear Config Cache
```bash
php artisan config:clear
```

### 3. Verify Setting
```bash
php artisan tinker
>>> config('app.import_verbose')
=> false
```

## 📈 Expected Results

| Import Size | Log File (Before) | Log File (After) | Time Saved |
|-------------|-------------------|------------------|------------|
| 1,000 rows  | ~5 MB            | ~500 KB         | ~2 min     |
| 5,000 rows  | ~25 MB           | ~2.5 MB         | ~5 min     |
| 10,000 rows | ~50 MB           | ~5 MB           | ~8 min     |

## 🐛 Troubleshooting

### Still getting detailed logs?
1. Check `.env` has `IMPORT_VERBOSE=false`
2. Run: `php artisan config:clear`
3. Restart queue workers if using queues

### Need detailed logs for one import?
1. Set `IMPORT_VERBOSE=true`
2. Run your import
3. Set back to `false`
4. Clear config: `php artisan config:clear`

### Timeout still occurring?
1. Verify verbose is disabled
2. Check other PHP settings (memory_limit, max_execution_time)
3. Consider chunking imports if file is > 20,000 rows

## 📝 Example Commands

```bash
# Check current setting
php artisan tinker --execute="echo config('app.import_verbose') ? 'true' : 'false';"

# Enable verbose mode temporarily
echo "IMPORT_VERBOSE=true" >> .env
php artisan config:clear

# Disable verbose mode
sed -i '/IMPORT_VERBOSE/d' .env
echo "IMPORT_VERBOSE=false" >> .env
php artisan config:clear

# View last 50 lines of log
tail -n 50 storage/logs/laravel.log

# View only errors and warnings
grep -E "ERROR|WARNING" storage/logs/laravel.log | tail -n 50

# Count log entries
grep "\[INFO\]" storage/logs/laravel.log | wc -l
```

## ✅ Checklist

Before running large import in production:
- [ ] `IMPORT_VERBOSE=false` in `.env`
- [ ] Config cache cleared
- [ ] Test import with 100 rows first
- [ ] Monitor log file size
- [ ] Check final summary appears
- [ ] Verify no timeout errors

## 🎯 Remember

- **Default is optimized**: No action needed for production
- **Enable only when debugging**: Set to true temporarily
- **Clear cache after changes**: Always run `config:clear`
- **Monitor first import**: Check logs and performance

## 📞 Support

Check documentation:
- `LOGGING_OPTIMIZATION_GUIDE.md` - Full user guide
- `PR_SUMMARY_LOGGING_OPTIMIZATION.md` - Implementation details
- `PAYMENT_HISTORY_IMPORT_LOGGING_GUIDE.md` - Original logging guide

---
**Last Updated**: 2024-01-15
**Version**: 1.0.0
