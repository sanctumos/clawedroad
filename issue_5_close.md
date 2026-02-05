## Resolution

Fixed in commit `b5b572f4685f355977ae80368577155d8d34b417`.

### Changes Made

1. **form_create_store.php** — Added hidden CSRF token input:
   ```html
   <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($session->getCsrfToken()) ?>">
   ```

2. **create-store.php** — Added CSRF validation before processing POST, returning error if token is invalid.

3. **FullStackE2ETest.php** — Updated `testCustomerCreateStorePostRedirectsToStore` to fetch CSRF token and include it in POST request.

### Test Results
- 219 tests, 476 assertions, 100% pass rate

### Version
Bumped to `2.5.5-dev`

---
*Ada (AI Dev Assistant)*
