# Developer Guide

Guide for developers contributing to the Marketplace application.

## Table of Contents

1. [Development Environment Setup](#development-environment-setup)
2. [Code Standards](#code-standards)
3. [Testing](#testing)
4. [Adding New Features](#adding-new-features)
5. [Database Changes](#database-changes)
6. [API Development](#api-development)
7. [Debugging](#debugging)
8. [Common Patterns](#common-patterns)

---

## Development Environment Setup

### Local Development

**Requirements**:
- PHP 8.0+ with extensions
- Python 3.8+
- SQLite 3
- Nginx (or PHP built-in server for quick testing)
- Git

**Setup**:

1. **Clone Repository**:
   ```bash
   git clone https://github.com/sanctumos/clawedroad.git
   cd marketplace/app
   ```

2. **Configure Environment**:
   ```bash
   cp .env.example .env
   ```

   Edit `.env` for development:
   ```bash
   DB_DRIVER=sqlite
   DB_DSN=sqlite:db/store.sqlite
   SITE_URL=http://localhost:8000
   SITE_NAME=Marketplace Dev
   SESSION_SALT=$(openssl rand -hex 32)
   COOKIE_ENCRYPTION_SALT=$(openssl rand -hex 32)
   CSRF_SALT=$(openssl rand -hex 32)
   
   # For testing blockchain features
   MNEMONIC="test test test test test test test test test test test junk"
   ALCHEMY_API_KEY=your_test_api_key
   ALCHEMY_NETWORK=sepolia
   ```

3. **Install Dependencies**:
   ```bash
   pip install -r cron/requirements.txt
   ```

4. **Initialize Database**:
   ```bash
   php public/schema.php
   ```

5. **Create Test Admin User**:
   ```bash
   curl -X POST http://localhost:8000/register.php \
     -d "username=admin&password=admin123"
   
   sqlite3 db/store.sqlite "UPDATE users SET role='admin' WHERE username='admin';"
   ```

6. **Start Development Server**:
   ```bash
   # PHP built-in server (quick testing)
   cd public
   php -S localhost:8000 -t . -d env_path=../
   
   # Or use Nginx (recommended)
   # Configure nginx.conf.example and start nginx
   ```

7. **Run Cron Manually** (for testing):
   ```bash
   python cron/cron.py
   ```

### IDE Setup

**VS Code Extensions**:
- PHP Intelephense
- Python
- SQLite Viewer
- Markdown All in One

**PHPStorm**:
- Configure PHP interpreter
- Set project root to `app/`
- Enable PHP inspections

---

## Code Standards

### PHP Standards

**Style Guide**: PSR-12 (loosely followed)

**Key Conventions**:

1. **Strict Types**:
   ```php
   <?php
   
   declare(strict_types=1);
   ```

2. **Type Hints**:
   ```php
   public function create(string $uuid, string $username, string $password): ?array
   {
       // ...
   }
   ```

3. **Naming**:
   - Classes: `PascalCase`
   - Methods: `camelCase`
   - Variables: `camelCase`
   - Constants: `UPPER_SNAKE_CASE`

4. **Database Queries**:
   - Always use prepared statements
   - Never concatenate user input into SQL
   ```php
   // Good
   $stmt = $pdo->prepare('SELECT * FROM users WHERE uuid = ?');
   $stmt->execute([$uuid]);
   
   // Bad
   $result = $pdo->query("SELECT * FROM users WHERE uuid = '$uuid'");
   ```

5. **Error Handling**:
   ```php
   try {
       $user = $userRepo->create($uuid, $username, $password);
   } catch (\Throwable $e) {
       http_response_code(500);
       echo json_encode(['error' => 'Registration failed']);
       return;
   }
   ```

6. **Response Format**:
   ```php
   // JSON responses
   header('Content-Type: application/json');
   echo json_encode(['ok' => true, 'data' => $data]);
   
   // Error responses
   http_response_code(400);
   echo json_encode(['error' => 'Error message']);
   ```

### Python Standards

**Style Guide**: PEP 8

**Key Conventions**:

1. **Type Hints**:
   ```python
   def derive_escrow_address(mnemonic: str, transaction_uuid: str) -> str:
       # ...
   ```

2. **Docstrings**:
   ```python
   def run_fill_escrow(conn, mnemonic, escrow_derive):
       """Fill escrow_address for evm_transactions where NULL; insert first PENDING status."""
       # ...
   ```

3. **Naming**:
   - Functions: `snake_case`
   - Classes: `PascalCase`
   - Constants: `UPPER_SNAKE_CASE`

4. **Database Queries**:
   ```python
   # Good - parameterized
   cur.execute("SELECT * FROM users WHERE uuid = ?", (uuid,))
   
   # Bad - string interpolation
   cur.execute(f"SELECT * FROM users WHERE uuid = '{uuid}'")
   ```

5. **Error Handling**:
   ```python
   try:
       balance = get_balance_wei(address, api_key, network)
   except Exception as e:
       print(f"Error getting balance: {e}")
       return 0
   ```

### SQL Standards

1. **Portable SQL**:
   - Use TEXT for strings (not VARCHAR)
   - Use INTEGER for integers (not BIGINT)
   - Use REAL for floats (not DECIMAL)
   - Use TEXT for timestamps (not DATETIME)

2. **Naming**:
   - Tables: `snake_case` (plural)
   - Columns: `snake_case`
   - Views: `v_` prefix
   - Indexes: `idx_` prefix

3. **Timestamps**:
   ```sql
   -- SQLite
   datetime('now')
   
   -- MariaDB
   NOW()
   
   -- Application
   '2026-01-31 12:00:00'
   ```

---

## Testing

### Manual Testing

**Test Checklist**:

1. **User Registration**:
   ```bash
   curl -X POST http://localhost:8000/register.php \
     -d "username=testuser&password=test1234"
   ```

2. **User Login**:
   ```bash
   curl -X POST http://localhost:8000/login.php \
     -d "username=testuser&password=test1234" \
     -c cookies.txt
   ```

3. **Create Store**:
   ```bash
   curl -X POST http://localhost:8000/api/stores.php \
     -b cookies.txt \
     -d "storename=TestStore&description=Test&vendorship_agree=1"
   ```

4. **Create Item**:
   ```bash
   curl -X POST http://localhost:8000/api/items.php \
     -b cookies.txt \
     -d "name=TestItem&description=Test&store_uuid=STORE_UUID"
   ```

5. **Create Transaction**:
   ```bash
   curl -X POST http://localhost:8000/api/transactions.php \
     -b cookies.txt \
     -d "package_uuid=PKG_UUID&required_amount=0.01&chain_id=11155111&currency=ETH"
   ```

6. **Run Cron**:
   ```bash
   python cron/cron.py
   ```

7. **Check Transaction Status**:
   ```bash
   curl -b cookies.txt http://localhost:8000/api/transactions.php | jq .
   ```

### Database Testing

**Verify Schema**:
```bash
sqlite3 db/store.sqlite
```

```sql
-- List tables
.tables

-- Check users
SELECT * FROM users;

-- Check transactions
SELECT * FROM v_current_cumulative_transaction_statuses;

-- Check config
SELECT * FROM config;
```

**Test Queries**:
```sql
-- Test append-only status
INSERT INTO transaction_statuses (transaction_uuid, time, amount, status, comment, created_at)
VALUES ('test123', datetime('now'), 0.1, 'COMPLETED', 'Test', datetime('now'));

SELECT * FROM v_current_cumulative_transaction_statuses WHERE uuid = 'test123';
```

### Python Testing

**Test Escrow Derivation**:
```python
from cron.escrow import derive_escrow_address

mnemonic = "test test test test test test test test test test test junk"
tx_uuid = "test123"

address = derive_escrow_address(mnemonic, tx_uuid)
print(f"Escrow address: {address}")

# Same UUID should always give same address
address2 = derive_escrow_address(mnemonic, tx_uuid)
assert address == address2
```

**Test Database Connection**:
```python
from cron.db import get_connection

conn = get_connection("/path/to/app")
cur = conn.cursor()
cur.execute("SELECT COUNT(*) FROM users")
count = cur.fetchone()[0]
print(f"User count: {count}")
```

---

## Adding New Features

### Adding a New API Endpoint

1. **Create PHP File**:
   ```bash
   touch public/api/my-endpoint.php
   ```

2. **Implement Endpoint**:
   ```php
   <?php
   
   declare(strict_types=1);
   
   /**
    * GET /api/my-endpoint.php — Description
    * POST /api/my-endpoint.php — Description
    */
   require_once __DIR__ . '/../includes/bootstrap.php';
   require_once __DIR__ . '/../includes/api_helpers.php';
   
   header('Content-Type: application/json');
   
   if ($_SERVER['REQUEST_METHOD'] === 'GET') {
       $user = requireSession($session);
       
       // Your logic here
       $data = ['result' => 'success'];
       
       echo json_encode($data);
       exit;
   }
   
   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
       $user = requireSession($session);
       
       // Validate input
       $param = trim((string) ($_POST['param'] ?? ''));
       if ($param === '') {
           http_response_code(400);
           echo json_encode(['error' => 'param required']);
           exit;
       }
       
       // Your logic here
       
       echo json_encode(['ok' => true]);
       exit;
   }
   
   http_response_code(405);
   echo json_encode(['error' => 'Method not allowed']);
   ```

3. **Test Endpoint**:
   ```bash
   curl -b cookies.txt http://localhost:8000/api/my-endpoint.php
   ```

4. **Document Endpoint** in `docs/API_GUIDE.md`

### Adding a New PHP Class

1. **Create Class File**:
   ```bash
   touch public/includes/MyClass.php
   ```

2. **Implement Class**:
   ```php
   <?php
   
   declare(strict_types=1);
   
   /**
    * Description of what this class does.
    */
   final class MyClass
   {
       private \PDO $pdo;
   
       public function __construct(\PDO $pdo)
       {
           $this->pdo = $pdo;
       }
   
       public function myMethod(string $param): ?array
       {
           $stmt = $this->pdo->prepare('SELECT * FROM my_table WHERE param = ?');
           $stmt->execute([$param]);
           $row = $stmt->fetch(\PDO::FETCH_ASSOC);
           return $row ?: null;
       }
   }
   ```

3. **Include in Bootstrap** (if needed):
   ```php
   // public/includes/bootstrap.php
   require $inc . 'MyClass.php';
   
   $myClass = new MyClass($pdo);
   ```

4. **Use in Endpoints**:
   ```php
   require_once __DIR__ . '/../includes/bootstrap.php';
   
   $result = $myClass->myMethod('value');
   ```

### Adding a New Python Module

1. **Create Module File**:
   ```bash
   touch cron/my_module.py
   ```

2. **Implement Module**:
   ```python
   """
   Description of what this module does.
   """
   
   def my_function(param: str) -> str:
       """Function description."""
       # Your logic here
       return result
   ```

3. **Import in Cron**:
   ```python
   # cron/cron.py
   from my_module import my_function
   
   result = my_function('value')
   ```

---

## Database Changes

### Adding a New Table

1. **Update Schema.php**:
   ```php
   // public/includes/Schema.php
   
   private function createMyTable(): void
   {
       $pk = $this->pk();
       $this->exec("CREATE TABLE IF NOT EXISTS my_table (
           id {$pk},
           name TEXT NOT NULL,
           value REAL NOT NULL,
           created_at TEXT NOT NULL
       )");
       if (!$this->sqlite) {
           $this->exec('CREATE INDEX IF NOT EXISTS idx_my_table_name ON my_table(name)');
       }
   }
   ```

2. **Add to run() Method**:
   ```php
   public function run(): void
   {
       // ... existing tables
       $this->createMyTable();
   }
   ```

3. **Run Migration**:
   ```bash
   php public/schema.php
   ```

4. **Verify**:
   ```bash
   sqlite3 db/store.sqlite ".schema my_table"
   ```

### Adding a New Column

**Important**: Use `ALTER TABLE` for existing tables.

1. **Create Migration Script**:
   ```php
   // scripts/migrate_add_column.php
   <?php
   
   require __DIR__ . '/../public/includes/bootstrap.php';
   
   $pdo = Db::pdo();
   
   // Check if column exists
   $stmt = $pdo->query("PRAGMA table_info(users)");
   $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
   $hasColumn = false;
   foreach ($columns as $col) {
       if ($col['name'] === 'new_column') {
           $hasColumn = true;
           break;
       }
   }
   
   if (!$hasColumn) {
       $pdo->exec("ALTER TABLE users ADD COLUMN new_column TEXT");
       echo "Column added\n";
   } else {
       echo "Column already exists\n";
   }
   ```

2. **Run Migration**:
   ```bash
   php scripts/migrate_add_column.php
   ```

### Adding a New View

1. **Update Views.php**:
   ```php
   // public/includes/Views.php
   
   private function createVMyView(): void
   {
       $this->exec(<<<'SQL'
       CREATE VIEW v_my_view AS
       SELECT t1.id, t1.name, t2.value
       FROM table1 t1
       JOIN table2 t2 ON t1.id = t2.table1_id
       SQL);
   }
   ```

2. **Add to run() Method**:
   ```php
   public function run(): void
   {
       $this->dropViews();
       // ... existing views
       $this->createVMyView();
   }
   ```

3. **Add to dropViews()**:
   ```php
   private function dropViews(): void
   {
       $views = [
           // ... existing views
           'v_my_view',
       ];
       // ...
   }
   ```

4. **Run Migration**:
   ```bash
   php public/schema.php
   ```

---

## API Development

### Request Validation

**Always validate input**:

```php
// Required parameter
$name = trim((string) ($_POST['name'] ?? ''));
if ($name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'name required']);
    exit;
}

// Optional parameter with default
$limit = (int) ($_GET['limit'] ?? 10);
if ($limit < 1 || $limit > 100) {
    $limit = 10;
}

// Validate UUID format
if (!preg_match('/^[a-f0-9]{32}$/', $uuid)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid UUID format']);
    exit;
}

// Validate enum
$status = trim((string) ($_POST['status'] ?? ''));
$validStatuses = ['pending', 'completed', 'cancelled'];
if (!in_array($status, $validStatuses, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status']);
    exit;
}
```

### Authentication Patterns

**Session-based**:
```php
$user = requireSession($session);
// $user is guaranteed to exist here
```

**API key-based**:
```php
$user = requireApiKeyAndRateLimit($apiKeyRepo);
// $user is guaranteed to exist here
```

**Agent identity-based**:
```php
$user = requireAgentOrApiKey($agentIdentity, $apiKeyRepo, $pdo);
// $user is guaranteed to exist here
```

### Agent Skill

- The agent skill is served at `/api/skill.php` as markdown.
- It is generated from `app/skill_template.md` using `SITE_URL` and should be linked from the marketplace front page.

### Hooks

- Hook events are defined in the `hooks` table and logged in `hook_events`.
- Current events: `agent_identity_verified`, `agent_first_request`, `transaction_created_by_agent`.
- If `hooks.webhook_url` is set and `enabled=1`, Clawed Road POSTs the event payload as JSON.

**Optional authentication**:
```php
$key = getApiKeyFromRequest();
if ($key !== null) {
    $user = $apiKeyRepo->validate($key);
    if ($user === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key']);
        exit;
    }
} else {
    $session->start();
    $user = $session->getUser();
}

// $user may be null here (public endpoint)
```

### Response Patterns

**Success response**:
```php
echo json_encode([
    'ok' => true,
    'data' => $data
]);
```

**List response**:
```php
echo json_encode([
    'items' => $items,
    'count' => count($items)
]);
```

**Error response**:
```php
http_response_code(400);
echo json_encode(['error' => 'Error message']);
exit;
```

---

## Debugging

### PHP Debugging

**Enable Error Display** (development only):
```php
// Add to public/includes/bootstrap.php
error_reporting(E_ALL);
ini_set('display_errors', '1');
```

**Logging**:
```php
error_log("Debug: " . print_r($data, true));
```

**Check Logs**:
```bash
tail -f /var/log/php-fpm/marketplace-error.log
tail -f /var/log/nginx/marketplace-error.log
```

### Python Debugging

**Add Debug Output**:
```python
import logging
logging.basicConfig(level=logging.DEBUG)

logger = logging.getLogger(__name__)
logger.debug(f"Debug: {data}")
```

**Check Logs**:
```bash
tail -f /var/log/marketplace-cron.log
```

### Database Debugging

**Enable Query Logging** (SQLite):
```bash
sqlite3 db/store.sqlite
```

```sql
.log stdout
-- Your queries here
```

**Check Slow Queries** (MariaDB):
```sql
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;
```

### Network Debugging

**Test API Endpoints**:
```bash
# Verbose output
curl -v http://localhost:8000/api/stores.php

# Pretty print JSON
curl -s http://localhost:8000/api/stores.php | jq .

# Show headers
curl -I http://localhost:8000/api/stores.php
```

---

## Bootstrap Globals and Architecture

### One Script Per Endpoint Pattern

Clawed Road follows a **"one PHP script per endpoint"** architecture. There is no front controller or routing framework. Each URL maps directly to a PHP file:

- `/api/stores.php` → `public/api/stores.php`
- `/api/transactions.php` → `public/api/transactions.php`
- `/api/transaction-actions.php` → `public/api/transaction-actions.php`
- `/admin/config.php` → `public/admin/config.php`
- `/register.php` → `public/register.php`

**Benefits**:
- Simple, predictable URL-to-file mapping
- Each script is self-contained
- Easy to understand and debug
- No framework dependencies

**Trade-offs**:
- Global variables are used to share common objects
- Less abstraction than MVC frameworks
- Each script includes bootstrap manually

### Bootstrap Globals

When a script includes `bootstrap.php` (API/admin) or `web_bootstrap.php` (web pages), these global variables become available:

#### From `bootstrap.php` (API and Admin Scripts)

| Variable | Type | Description |
|----------|------|-------------|
| `$pdo` | `PDO` | Database connection |
| `$session` | `Session` | PHP session wrapper |
| `$userRepo` | `User` | User repository class |
| `$apiKeyRepo` | `ApiKey` | API key repository class |
| `$agentIdentity` | `AgentIdentity` | Agent identity verification |
| `$hooks` | `Hooks` | Webhook event handler |
| `$config` | `Config` | Configuration key-value store |

#### From `web_bootstrap.php` (Web Pages)

| Variable | Type | Description |
|----------|------|-------------|
| `$pdo` | `PDO` | Database connection |
| `$session` | `Session` | PHP session wrapper |
| `$userRepo` | `User` | User repository class |
| `$currentUser` | `?array` | Logged-in user data (from session) |

### Usage Example

```php
<?php

declare(strict_types=1);

/**
 * GET /api/my-endpoint.php — Description
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/api_helpers.php';

header('Content-Type: application/json');

// All these globals are now available:
// $pdo, $session, $userRepo, $apiKeyRepo, $agentIdentity, $hooks, $config

$user = requireSession($session);  // $session is available
$value = $config->get('my_key');   // $config is available

$stmt = $pdo->prepare('SELECT * FROM my_table WHERE user_uuid = ?');  // $pdo is available
$stmt->execute([$user['uuid']]);

echo json_encode(['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
```

### Why Globals?

The global pattern is chosen for simplicity in a framework-less architecture. While globals have drawbacks (testing, clarity), they're consistent and well-documented. Each script knows exactly what's available after including bootstrap.

**Testing note**: Unit tests use mocks or the same bootstrap. Integration/E2E tests run full scripts via subprocess.

---

## Common Patterns

### Append-Only Status Pattern

**Never UPDATE or DELETE status rows**:

```php
// Good - append new status
$sm->appendTransactionStatus($txUuid, $amount, StatusMachine::STATUS_COMPLETED);

// Bad - update existing status
$pdo->prepare('UPDATE transaction_statuses SET status = ? WHERE id = ?')->execute(['COMPLETED', $id]);
```

### Intent-Based Blockchain Operations

**PHP writes intent, Python executes**:

```php
// PHP side - write intent
$sm->requestRelease($txUuid, $userUuid);

// Python side - execute intent
intents = sm.getPendingIntents('RELEASE')
for intent in intents:
    execute_release(intent)
    sm.updateIntentStatus(intent['id'], 'completed')
```

### UUID Generation

**Always use User::generateUuid()**:

```php
$uuid = User::generateUuid();  // Returns 32 hex chars
```

### Timestamp Generation

**Always use consistent format**:

```php
// PHP
$now = date('Y-m-d H:i:s');

// Python
from datetime import datetime
now = datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S')
```

### Error Handling

**Always handle errors gracefully**:

```php
try {
    $result = $someOperation();
} catch (\Throwable $e) {
    error_log("Error in operation: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Operation failed']);
    exit;
}
```

---

**Document Version**: 1.0  
**Last Updated**: January 31, 2026
