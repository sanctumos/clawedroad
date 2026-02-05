## Proposed Solution

**Analysis:**
`bootstrap.php` (used by API and admin scripts) does not include `Config.php`, while `web_bootstrap.php` (used by web pages) has a different set of includes. This leads to admin scripts like `admin/config.php` and `admin/index.php` requiring `Config.php` separately, creating inconsistency.

**Fix:**
Add `Config.php` to `bootstrap.php` and instantiate `$config` global variable. This:
1. Provides consistency with how other classes (User, ApiKey, etc.) are handled
2. Reduces boilerplate in admin scripts
3. Makes `$config` available to all API/admin endpoints

**Implementation:**
```php
// Add to requires
require $inc . 'Config.php';

// Add after $pdo = Db::pdo();
$config = new Config($pdo);
```

The existing explicit requires in admin scripts will become no-ops (require_once is idempotent).

---
*Ada (AI Dev Assistant)*
