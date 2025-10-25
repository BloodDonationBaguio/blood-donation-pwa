# Donor Status Update Error Fix

## Problem Description

When updating a donor's status through the admin interface, users experienced an error message:
> "Failed to update donor status: Failed to update donor status. Details: Unknown database error"

However, upon refreshing the page, the status was actually updated successfully. This indicated that:
1. The database update was working
2. But the transaction was rolling back due to a secondary error
3. Causing a false error message to be displayed

## Root Causes

There were **two critical issues** causing the errors:

### Issue 1: Column Name Mismatch

The first issue was a **column name mismatch** in the `donations_new` table:

- **Table Schema** (line 527 in `includes/enhanced_donor_management.php`):
  ```sql
  CREATE TABLE IF NOT EXISTS donations_new (
      ...
      status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
      ...
  )
  ```

- **INSERT Statements** (lines 133 and 416):
  ```php
  INSERT INTO donations_new (donor_id, donation_date, blood_type, donation_status, ...)
  ```

The code was trying to insert into a column named `donation_status`, but the table was created with a column named `status`. This caused SQL errors when updating a donor to 'served' status, which triggers a donation record insertion.

### Issue 2: DDL Statements Inside Transactions

The second issue was **DDL statements (CREATE TABLE, ALTER TABLE) inside transactions** that automatically commit:

MySQL automatically commits transactions when DDL statements are executed. The code had:

1. `logAdminAction()` function containing `CREATE TABLE IF NOT EXISTS` (executed during transaction)
2. Multiple `ALTER TABLE` statements inside the `markDonorServed()` transaction
3. These DDL statements would **auto-commit** the transaction
4. When the code tried to commit again, it would fail with "There is no active transaction"

### Why the Status Still Updated

The status update happened **before** the problematic DDL or donation record insertion. When errors occurred:
1. The donor status was already updated
2. DDL statements auto-committed the transaction (partially committing changes)
3. Subsequent operations failed
4. Error was thrown, but some changes persisted
5. Making it appear as if the operation both failed and succeeded

## Fix Applied

### 1. Fixed Column Names in INSERT Statements

Changed both occurrences from `donation_status` to `status`:

**File: `includes/enhanced_donor_management.php`**

- Line 133 (in `updateDonorStatus` function)
- Line 416 (in `markDonorServed` function)

```php
// Before:
INSERT INTO donations_new (donor_id, donation_date, blood_type, donation_status, created_at)

// After:
INSERT INTO donations_new (donor_id, donation_date, blood_type, status, created_at)
```

### 2. Fixed Transaction Auto-Commit Issues

**Created `ensureAuditLogTableExists()` function** in `includes/admin_actions.php`:
- Separated table creation from logging function
- Call this BEFORE starting transactions to avoid auto-commit

**Updated `logAdminAction()` function**:
- Removed `CREATE TABLE IF NOT EXISTS` from inside the function
- Added comment warning about DDL auto-commit

**Updated all transaction-based functions** in `includes/enhanced_donor_management.php`:
- `updateDonorStatus()` - Added `ensureAuditLogTableExists()` call before transaction
- `approveDonor()` - Added `ensureAuditLogTableExists()` call before transaction  
- `markDonorUnserved()` - Added `ensureAuditLogTableExists()` call before transaction
- `markDonorServed()` - Added `ensureAuditLogTableExists()` call before transaction
- `markDonorServed()` - Moved all `ALTER TABLE` statements outside transaction
- `markDonorServed()` - Removed redundant DDL checks inside transaction

### 3. Improved Error Handling

Enhanced error reporting to provide detailed error messages:

**File: `includes/enhanced_donor_management.php`**
- Added stack trace logging
- Added global error variable to pass detailed error messages to the caller

**File: `admin_enhanced_donor_management.php`**
- Improved error retrieval from the update function
- Added detailed logging for debugging

### 4. Migration Script

Created `fix_donations_table_column.php` to fix existing databases where the table might already exist with the wrong column name.

## Testing the Fix

### 1. Run the Migration (if needed)

If your database already has the `donations_new` table with the wrong column:

```
http://localhost/blood-donation-pwa/fix_donations_table_column.php
```

This will:
- Check if the table exists
- Rename `donation_status` to `status` if needed
- Display the current table structure

### 2. Test Status Updates

1. Go to the Enhanced Donor Management page
2. Select a donor
3. Click "Update Status"
4. Change the status to any value (especially "Served" which triggers donation record creation)
5. Add optional notes
6. Click "Update Status"

**Expected Result:**
- ✓ Success message: "Donor status updated successfully!"
- ✓ Page reloads showing the new status
- ✓ No error messages

### 3. Verify Donation Records

For donors marked as "Served", check that donation records are created:

```sql
SELECT * FROM donations_new ORDER BY created_at DESC LIMIT 10;
```

You should see new donation records with `status = 'completed'`.

## Files Modified

1. **`includes/enhanced_donor_management.php`**
   - Fixed column name `donation_status` → `status` in `updateDonorStatus()` (line 133)
   - Fixed column name `donation_status` → `status` in `markDonorServed()` (line 416)
   - Added `ensureAuditLogTableExists()` call before all transactions
   - Moved DDL statements outside transactions in `markDonorServed()`
   - Improved error handling with stack traces and global error variable

2. **`includes/admin_actions.php`**
   - Created new `ensureAuditLogTableExists()` function
   - Removed `CREATE TABLE` from inside `logAdminAction()` function
   - Added comments warning about DDL auto-commit issues

3. **`admin_enhanced_donor_management.php`**
   - Enhanced error reporting in AJAX handler
   - Added detailed error logging with stack traces
   - Improved error message retrieval from functions

4. **`fix_donations_table_column.php`** (new file)
   - Migration script to fix existing database schema
   - Renames `donation_status` column to `status` if needed

5. **`DONOR_STATUS_UPDATE_FIX.md`** (new file)
   - Comprehensive documentation of the issues and fixes

## Affected Statuses

This fix affects donors being updated to **"Served"** status, as this status triggers:
1. Donor status update to 'served'
2. Creation of a donation record in `donations_new` table
3. Creation of a blood unit in the inventory (automatically)
4. Sending thank you email to the donor

All other status updates (pending, approved, unserved, rejected) were not affected by this bug as they don't create donation records.

## Prevention

To prevent similar issues in the future:
1. ✓ Keep column names consistent across CREATE TABLE and INSERT statements
2. ✓ Use detailed error logging with stack traces
3. ✓ Test all code paths, especially those in transactions
4. ✓ Run migration scripts when schema changes are made

## Support

If you still experience issues after applying this fix:
1. Check the error logs at `logs/donor_served.log`
2. Check PHP error logs
3. Run the migration script to verify table structure
4. Check browser console for JavaScript errors

