## Resolution

Fixed in commit `aa415269568b0da1626b5047b2c60cd7536adbe6`.

### Changes Made

1. **bootstrap.php** — Added `Config.php` to includes and instantiate `$config` global:
   ```php
   require_once $inc . 'Config.php';
   // ...
   $config = new Config($pdo);
   ```

2. **Changed all requires to `require_once`** for idempotency, preventing "class already declared" errors.

3. **tests/bootstrap.php** — Updated to use `require_once` for classes now loaded by bootstrap.php.

### Result
- Admin and API scripts now have `$config` available automatically
- Existing `require_once` statements in admin/config.php and admin/index.php become no-ops (safe)
- Consistent with how other classes (User, ApiKey, etc.) are handled

### Test Results
- 219 tests, 477 assertions, 100% pass rate

### Version
Bumped to `2.5.8-dev`

---
*Ada (AI Dev Assistant)*
