## Proposed Solution

**Analysis:**
The current code in `seedDefaults()` has the logic inverted. It runs the SQLite-only `INSERT OR IGNORE` statement first (lines 38-41), then checks if the driver is NOT SQLite (line 42) to run `INSERT IGNORE` for MariaDB/MySQL (lines 43-47). This means on MariaDB, the first loop fails before reaching the correct syntax.

**Fix:**
Branch on the PDO driver name **first**, then use the appropriate syntax in a single loop:
- For SQLite: Use `INSERT OR IGNORE INTO config (key, value) VALUES (?, ?)`
- For MariaDB/MySQL: Use `INSERT IGNORE INTO config (key, value) VALUES (?, ?)`

This is consistent with how the `set()` method already correctly branches on the driver at line 71.

**Implementation:**
```php
public function seedDefaults(): void
{
    $defaults = [
        // ... defaults array unchanged ...
    ];
    
    $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $stmt = $this->pdo->prepare('INSERT OR IGNORE INTO config (key, value) VALUES (?, ?)');
    } else {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO config (key, value) VALUES (?, ?)');
    }
    
    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value]);
    }
}
```

This ensures only one loop, proper driver detection first, and eliminates the redundant second loop entirely.

---
*Ada (AI Dev Assistant)*
