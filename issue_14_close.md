## Resolution

Fixed in commit `3bc9d73ab6ee8468a55b2524fd08b619959853ed`.

### Changes Made

1. **Schema.php** — Added CHECK constraint to `storename` column:
   ```sql
   CHECK (LENGTH(storename) >= 1 AND LENGTH(storename) <= 16)
   ```

2. **Documentation** — Added docblock comment to `createStores()` method explaining the 16-char limit.

### Note
For existing databases, the CHECK constraint only applies when the table is first created. Existing deployments would need a migration to add the constraint to existing tables. SQLite has limited ALTER TABLE support for constraints.

### Test Results
- 219 tests, 477 assertions, 100% pass rate

### Version
Bumped to `2.5.7-dev`

---
*Ada (AI Dev Assistant)*
