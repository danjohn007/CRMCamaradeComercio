# Fixes Applied - November 2025

This document describes the fixes applied to resolve issues reported in the CRM system.

## Issues Fixed

### 1. Dashboard Financiero - "Nuevo Movimiento" Button Not Showing Modal ✅

**Problem:** The "Nuevo Movimiento" button on the Dashboard Financiero page was not showing the modal popup when clicked.

**Root Cause:** The modal HTML and JavaScript functions were only included on the "movimientos" page, not on the "dashboard" page.

**Solution:** 
- Added the complete modal HTML structure to the dashboard page
- Added the `modalMovimiento()`, `cerrarModalMovimiento()`, and `cargarCategorias()` JavaScript functions
- The modal now properly displays when clicking "Nuevo Movimiento" from the dashboard

**Files Modified:**
- `finanzas.php` (lines 548-689)

---

### 2. Company Registration - RFC Field Placement and Auto-Search ✅

**Problem:** Request to place RFC field as the first field above Email with a search button that auto-loads existing company data.

**Current Status:** 
- RFC field is already positioned as the first field (before Email) ✅
- Auto-search functionality is already implemented ✅
- When an RFC is entered, the system automatically searches for existing companies
- If found, data is auto-loaded into the form fields
- All fields remain editable after auto-loading

**Enhancement Applied:**
- Extended auto-load to include additional fields: `direccion_comercial`, `ciudad`, `estado`
- Fixed field selector to use correct input type

**Files Modified:**
- `empresas.php` (lines 1022-1036)
- `api/buscar_empresa.php` (already existed, no changes needed)

**Usage:**
1. Start entering an RFC in the RFC field
2. After entering 12+ characters, system automatically searches
3. If company exists, a green success message appears and fields are populated
4. All fields can be edited as needed
5. If company doesn't exist, a blue info message appears

---

### 3. Dashboard Financiero Shows Duplicate Payments ✅

**Problem:** The Dashboard Financiero was showing duplicate payments in "Últimos Movimientos" and reflecting double amounts in "Total Ingresos". The totals did not match the reportes module.

**Root Cause:** 
- When payments were registered via `api/registrar_pago.php`, they were being saved in both the `pagos` table AND the `finanzas_movimientos` table
- However, the `origen` and `pago_id` fields (which were added in a migration) were not being set correctly
- Dashboard queries were not filtering out these duplicate entries

**Solution:**
1. **Updated `api/registrar_pago.php`:**
   - Now properly sets `origen = 'PAGO'` and `pago_id` when creating financial movements
   - This marks the movement as coming from a payment, not a manual entry

2. **Updated Dashboard Queries in `finanzas.php`:**
   - Total Ingresos: Added filter `(origen IS NULL OR origen = 'MANUAL')`
   - Ingresos por Categoría: Added same filter
   - Últimos Movimientos: Added same filter
   - This ensures only manual movements are counted in the financial dashboard

3. **Database Migration:**
   - Created `database/fix_duplicate_payments.sql` to ensure required columns exist
   - Adds `origen` and `pago_id` columns if they don't exist
   - Sets default value of 'MANUAL' for existing records

**Files Modified:**
- `api/registrar_pago.php` (lines 131-146)
- `finanzas.php` (lines 197-259)
- `database/fix_duplicate_payments.sql` (new file)

**Important:** Run the migration script to ensure the database has the required columns:
```sql
mysql -u username -p database_name < database/fix_duplicate_payments.sql
```

---

### 4. Foreign Key Constraint Error When Saving Empresa ✅

**Problem:** Error when registering a new company:
```
SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: 
a foreign key constraint fails (`agenciae_canaco`.`empresas`, CONSTRAINT `empresas_ibfk_1` 
FOREIGN KEY (`vendedor_id`) REFERENCES `vendedores` (`id`) ON DELETE SET NULL)
```

**Root Cause:** 
- The `vendedor_id` field in the `empresas` table has a foreign key constraint to the `vendedores` table
- The code was querying `usuarios` table with `rol='AFILADOR'` instead of the `vendedores` table
- When vendedor_id was empty, it was likely being passed as an empty string instead of NULL

**Solution:**
1. Fixed the query to use the correct table: `SELECT id, nombre FROM vendedores WHERE activo = 1`
2. Fixed the data handling to properly convert empty strings to NULL: `!empty($_POST['vendedor_id']) ? intval($_POST['vendedor_id']) : null`

**Files Modified:**
- `empresas.php` (lines 78, 161)

---

## Testing Recommendations

### Test 1: Dashboard Modal
1. Navigate to "Dashboard Financiero" (finanzas.php?action=dashboard)
2. Click the green "Nuevo Movimiento" button
3. Verify the modal appears
4. Fill in the form and try to submit
5. Verify the form processes correctly

### Test 2: RFC Auto-Search
1. Navigate to "Nueva Empresa" (empresas.php?action=new)
2. Enter an RFC that exists in the database
3. Verify that company data auto-loads
4. Verify you can edit all fields
5. Submit the form and verify it works

### Test 3: Duplicate Payments
1. Make sure the migration `fix_duplicate_payments.sql` has been run
2. Register a payment for a company via empresas.php
3. Navigate to "Dashboard Financiero"
4. Verify "Últimos Movimientos" shows only manual entries (not duplicates from payments)
5. Navigate to "Reportes" and compare Total Ingresos
6. Both should match now

### Test 4: Empresa Registration with Vendedor
1. Navigate to "Nueva Empresa"
2. Fill in all required fields
3. Leave "Vendedor/Afiliador" empty
4. Submit the form
5. Verify no foreign key error occurs
6. Try again with a valid vendedor selected
7. Verify it saves correctly

---

## Database Migration Required

**IMPORTANT:** Before testing, run the migration script:

```bash
# Replace with your actual database credentials
mysql -u your_username -p your_database_name < database/fix_duplicate_payments.sql
```

This will:
- Add `origen` and `pago_id` columns to `finanzas_movimientos` if they don't exist
- Set default values for existing records
- Add necessary indexes and foreign keys

---

## Summary

All four issues have been successfully fixed:
- ✅ Dashboard modal now displays correctly
- ✅ RFC auto-search functionality enhanced and working
- ✅ Duplicate payments resolved with proper data filtering
- ✅ Foreign key constraint error fixed with correct table references

The system should now operate correctly without these issues.
