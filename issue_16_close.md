## Resolution

Fixed in commit `fc92e21c19059f1569c1cd2b11cb118be0b090c3`.

### Changes Made

Added "Bootstrap Globals and Architecture" section to `docs/app/DEVELOPER_GUIDE.md`:

1. **One Script Per Endpoint Pattern** — Explained the URL-to-file mapping architecture (no front controller)

2. **Bootstrap Globals Table** — Documented all globals:
   - From `bootstrap.php`: `$pdo`, `$session`, `$userRepo`, `$apiKeyRepo`, `$agentIdentity`, `$hooks`, `$config`
   - From `web_bootstrap.php`: `$pdo`, `$session`, `$userRepo`, `$currentUser`

3. **Usage Example** — Provided code showing how to use globals in a new endpoint

4. **Rationale** — Explained why globals are used and the trade-offs

### Test Results
- 219 tests, 477 assertions, 100% pass rate

### Version
Bumped to `2.5.9-dev`

---
*Ada (AI Dev Assistant)*
