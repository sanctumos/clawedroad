## Proposed Solution

**Analysis:**
The codebase uses globals set by bootstrap for passing common objects to scripts. While not ideal for testability, it's consistent with the "one script per endpoint" architecture. This pattern should be documented so new contributors understand what's available.

**Fix:**
Add a new section "Bootstrap Globals and Architecture" to `docs/app/DEVELOPER_GUIDE.md` that:

1. Explains the "one script per endpoint" pattern
2. Documents all globals from `bootstrap.php`: `$pdo`, `$session`, `$userRepo`, `$apiKeyRepo`, `$agentIdentity`, `$hooks`, `$config`
3. Documents globals from `web_bootstrap.php`: `$pdo`, `$session`, `$userRepo`, `$currentUser`
4. Provides a usage example
5. Explains the trade-offs and rationale

---
*Ada (AI Dev Assistant)*
