## Resolution

Fixed in commit `de389c6e810de1ccea376f653bb75b78684d6f64`.

### Changes Made

**api/transactions.php** — GET endpoint now properly scopes transactions:
- Returns transactions where user is the **buyer** (`buyer_uuid = user_uuid`)
- Returns transactions for **stores the user belongs to** (via `store_users` join)
- Added `ORDER BY updated_at DESC` for consistent results

### Before (vulnerable)
```php
$stmt = $pdo->query('SELECT * FROM v_current_cumulative_transaction_statuses LIMIT 100');
```
All users saw the same first 100 transactions.

### After (secure)
Queries `store_users` to find user's associated stores, then filters by `buyer_uuid = ?` OR `store_uuid IN (?)`.

### Test Results
- 219 tests, 477 assertions, 100% pass rate

### Version
Bumped to `2.5.6-dev`

---
*Ada (AI Dev Assistant)*
