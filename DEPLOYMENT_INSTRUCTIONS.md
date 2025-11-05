# Deployment Instructions for CRM Fixes

## Overview
This document provides step-by-step instructions for deploying the fixes to the CRM Camara de Comercio system.

## Pre-Deployment Checklist

- [ ] Backup current database
- [ ] Backup current code files
- [ ] Review all changes in the PR
- [ ] Ensure you have database credentials ready

## Deployment Steps

### Step 1: Backup Database

```bash
# Create a backup of the current database
mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Step 2: Pull Latest Code

```bash
# Navigate to project directory
cd /path/to/CRMCamaradeComercio

# Pull the latest changes from the branch
git pull origin copilot/fix-dashboard-financial-issues
```

### Step 3: Run Database Migration

**IMPORTANT:** This step is critical for fixing the duplicate payments issue.

```bash
# Run the migration script
mysql -u username -p database_name < database/fix_duplicate_payments.sql
```

When prompted, enter your database password.

Expected output:
```
Migration completed successfully. Columns origen and pago_id have been added to finanzas_movimientos table.
```

### Step 4: Verify Migration

Check that the columns were added successfully:

```sql
-- Login to MySQL
mysql -u username -p database_name

-- Check the table structure
DESCRIBE finanzas_movimientos;

-- You should see:
-- - origen VARCHAR(50) DEFAULT 'MANUAL'
-- - pago_id INT NULL
```

### Step 5: Clear Cache (if applicable)

If your application uses caching:

```bash
# Clear PHP opcache if enabled
# This depends on your server setup
# Example for Apache:
sudo service apache2 restart

# Example for Nginx with PHP-FPM:
sudo service php-fpm restart
sudo service nginx restart
```

### Step 6: Test the Fixes

#### Test 1: Dashboard Modal
1. Navigate to: `https://your-domain.com/finanzas.php?action=dashboard`
2. Click the green "Nuevo Movimiento" button
3. **Expected:** Modal popup appears with form fields
4. Fill in the form and submit
5. **Expected:** Movement is saved successfully

#### Test 2: RFC Auto-Search
1. Navigate to: `https://your-domain.com/empresas.php?action=new`
2. Enter an RFC that exists in your database (12-13 characters)
3. **Expected:** Green message appears and fields auto-populate
4. Verify you can edit all fields
5. Submit the form
6. **Expected:** Company saves successfully

#### Test 3: No Duplicate Payments
1. Register a payment for a company via `empresas.php`
2. Navigate to Dashboard Financiero
3. Check "Últimos Movimientos"
4. **Expected:** Only one entry per payment (no duplicates)
5. Compare "Total Ingresos" with Reportes module
6. **Expected:** Both totals match

#### Test 4: Empresa with Vendedor
1. Navigate to Nueva Empresa
2. Fill in required fields
3. **Test A:** Leave "Vendedor/Afiliador" empty and submit
4. **Expected:** No error, saves successfully
5. **Test B:** Select a vendedor and submit
6. **Expected:** Saves successfully with vendedor

## Troubleshooting

### Issue: Migration fails with "Column already exists"
**Solution:** This is normal if the migration was already run. The migration uses `ADD COLUMN IF NOT EXISTS`, so it's safe to run multiple times.

### Issue: Modal doesn't appear
**Check:**
- Browser console for JavaScript errors
- Ensure file permissions are correct: `chmod 644 finanzas.php`
- Clear browser cache (Ctrl+Shift+R or Cmd+Shift+R)

### Issue: Still seeing duplicate payments
**Check:**
- Verify migration ran successfully
- Check that `origen` column exists and has 'MANUAL' or 'PAGO' values
- Run this query to verify:
```sql
SELECT COUNT(*) as manual_count FROM finanzas_movimientos WHERE origen IS NULL OR origen = 'MANUAL';
SELECT COUNT(*) as pago_count FROM finanzas_movimientos WHERE origen = 'PAGO';
```

### Issue: Foreign key error persists
**Check:**
- Ensure `vendedores` table has data: `SELECT * FROM vendedores LIMIT 5;`
- If empty, you may need to migrate data or create vendors

## Rollback Procedure

If issues arise and you need to rollback:

```bash
# Restore code
git reset --hard HEAD~4  # Goes back 4 commits (adjust as needed)

# Restore database
mysql -u username -p database_name < backup_YYYYMMDD_HHMMSS.sql
```

## Post-Deployment Verification

- [ ] Dashboard modal works
- [ ] RFC auto-search works
- [ ] No duplicate payments visible
- [ ] Company registration works (with/without vendedor)
- [ ] Totals match between Dashboard and Reportes
- [ ] No console errors in browser
- [ ] No PHP errors in server logs

## Support

If you encounter issues not covered in this guide:

1. Check PHP error logs: `/var/log/apache2/error.log` or `/var/log/nginx/error.log`
2. Check MySQL error logs
3. Review browser console for JavaScript errors
4. Contact development team with:
   - Steps to reproduce
   - Error messages
   - Browser/server details

## Summary of Changes

### Modified Files:
1. `finanzas.php` - Added modal, fixed duplicate payment queries, security improvements
2. `empresas.php` - Fixed vendedor query, enhanced RFC auto-search
3. `api/registrar_pago.php` - Fixed parameters, added origen/pago_id fields

### New Files:
1. `database/fix_duplicate_payments.sql` - Database migration
2. `FIXES_APPLIED.md` - Detailed documentation
3. `DEPLOYMENT_INSTRUCTIONS.md` - This file

## Database Schema Changes

### Table: finanzas_movimientos
- **Added:** `origen VARCHAR(50) DEFAULT 'MANUAL'` - Tracks movement source
- **Added:** `pago_id INT NULL` - Links to pagos table when origen='PAGO'
- **Added:** Index on `origen` column for performance

## Security Improvements

- All HTML attributes properly escaped with `e()` function
- Date outputs use `json_encode()` or `e()` to prevent XSS
- Null checks added before DOM manipulation
- SQL parameter counts verified

---

**Last Updated:** November 5, 2025  
**Version:** 1.0  
**Tested On:** PHP 7.4+, MySQL 5.7+
