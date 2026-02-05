## Proposed Solution

**Analysis:**
The codebase has inconsistent PDO constant style:
- 23 occurrences use `\PDO::FETCH_ASSOC` (with backslash)
- 56 occurrences use `PDO::FETCH_ASSOC` (without backslash)

Both work in non-namespaced code, but `\PDO::FETCH_ASSOC` is more explicit and future-proof.

**Fix:**
Standardize all `PDO::FETCH_ASSOC` to `\PDO::FETCH_ASSOC` across `app/public/` and `app/tests/`.

---
*Ada (AI Dev Assistant)*
