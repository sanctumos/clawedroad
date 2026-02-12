# Architecture Documentation

## System Architecture Overview

The marketplace application follows a hybrid PHP/Python architecture with clear separation of concerns between web operations and blockchain operations.

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                         Client Layer                         │
│  (Web Browsers, Mobile Apps, API Clients)                   │
└────────────────┬────────────────────────────────────────────┘
                 │ HTTP/HTTPS
                 ▼
┌─────────────────────────────────────────────────────────────┐
│                      Nginx Web Server                        │
│  - Static file serving                                       │
│  - PHP-FPM proxy                                            │
│  - SSL termination                                          │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│                    PHP Application Layer                     │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Public Endpoints (public/)                          │  │
│  │  - index.php, login.php, register.php, logout.php    │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  API Endpoints (public/api/)                         │  │
│  │  - stores, items, transactions, keys, deposits       │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Admin Endpoints (public/admin/)                     │  │
│  │  - config, tokens                                    │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Core Classes (public/includes/)                     │  │
│  │  - User, Session, ApiKey, Config, Db, StatusMachine  │  │
│  └──────────────────────────────────────────────────────┘  │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│                      Database Layer                          │
│  SQLite (dev) / MariaDB (prod)                              │
│  - User data, stores, items, transactions                   │
│  - Transaction status log (append-only)                     │
│  - Transaction intents (for Python cron)                    │
└─────────────────────────────────────────────────────────────┘
                 ▲
                 │
                 │ (reads/writes)
                 │
┌────────────────┴────────────────────────────────────────────┐
│                  Python Cron Layer (cron/)                   │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Main Cron (cron.py)                                 │  │
│  │  - Scheduled every 1-5 minutes                       │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Tasks (tasks.py)                                    │  │
│  │  - Fill escrow addresses                            │  │
│  │  - Poll PENDING transactions                        │  │
│  │  - Fail old PENDING transactions                    │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Escrow Module (escrow.py)                          │  │
│  │  - HD wallet derivation (BIP-32/44)                 │  │
│  └──────────────────────────────────────────────────────┘  │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│                    Blockchain Layer                          │
│  Alchemy API (alchemy_client.py)                            │
│  - Balance queries (eth_getBalance)                         │
│  - Price feeds (ETH/USD)                                    │
│  - RPC calls to Ethereum networks                           │
└─────────────────────────────────────────────────────────────┘
```

## Component Interactions

### Transaction Creation Flow

```
1. User creates transaction via POST /api/transactions.php
   ├─ PHP validates package_uuid, buyer authentication
   ├─ PHP inserts into transactions table
   ├─ PHP inserts into evm_transactions (escrow_address = NULL)
   └─ Returns transaction UUID to user

2. Python cron (run_fill_escrow) detects NULL escrow_address
   ├─ Derives HD address from mnemonic + transaction_uuid
   ├─ Updates evm_transactions.escrow_address
   └─ Inserts first transaction_status (PENDING)

3. User sends funds to escrow_address (off-platform)

4. Python cron (run_update_pending) polls balances
   ├─ Queries Alchemy API for escrow_address balance
   ├─ Compares balance to required_amount (with tolerance)
   └─ If funded, inserts transaction_status (COMPLETED)

5. Vendor ships product, marks as shipped (future feature)

6. Buyer confirms receipt (annotation only), vendor/staff requests release
   ├─ Buyer confirm: updates transactions.buyer_confirmed_at
   ├─ Release request via web POST /payment.php (action=release)
   ├─ Or API POST /api/transaction-actions.php (action=release)
   └─ PHP inserts transaction_intent (RELEASE)

7. Python cron inserts transaction_status (RELEASED)
```

### Authentication Flow

#### Session-Based (Web UI)

```
1. User submits login form (POST /login.php)
   ├─ PHP validates username/password
   ├─ PHP creates session
   ├─ PHP stores user data in $_SESSION
   └─ Returns success

2. User makes authenticated request
   ├─ PHP reads session cookie
   ├─ PHP retrieves user from $_SESSION
   └─ Proceeds with request
```

#### API Key-Based (Programmatic)

```
1. User creates API key (POST /api/keys.php, requires session)
   ├─ PHP generates 64-char hex key
   ├─ PHP stores key in api_keys table
   └─ Returns key to user (one-time display)

2. Client includes key in request
   ├─ Header: Authorization: Bearer {key}
   ├─ Header: X-API-Key: {key}
   └─ Query: ?token={key}

3. PHP validates key
   ├─ Looks up key in api_keys table
   ├─ Checks rate limit (60/min)
   ├─ Records request in api_key_requests
   └─ Returns user data (inherits user role)
```

## Data Flow Patterns

### Append-Only Status Machine

The transaction status system is **append-only** to maintain a complete audit trail.

```
transaction_statuses table:
┌────┬──────────────┬─────────────────────┬────────┬───────────┐
│ id │ tx_uuid      │ time                │ amount │ status    │
├────┼──────────────┼─────────────────────┼────────┼───────────┤
│ 1  │ abc123...    │ 2026-01-31 12:00:00 │ 0.0    │ PENDING   │
│ 2  │ abc123...    │ 2026-01-31 12:05:00 │ 0.1    │ COMPLETED │
│ 3  │ abc123...    │ 2026-01-31 13:00:00 │ 0.1    │ RELEASED  │
└────┴──────────────┴─────────────────────┴────────┴───────────┘

Current status = row with MAX(time) for each tx_uuid
Views (v_current_transaction_statuses) provide this automatically
```

**Benefits**:
- Complete audit trail
- No data loss from overwrites
- Easy rollback/replay
- Dispute resolution evidence

**Trade-offs**:
- Table grows linearly with status changes
- Queries require MAX() aggregation (solved by views)
- No direct "current status" column

### Intent-Based Blockchain Operations

PHP never signs blockchain transactions. Instead, it writes "intent" records that Python executes.

```
PHP Side (web/API request):
┌─────────────────────────────────────────────────────────┐
│ User clicks "Release Funds"                             │
│   ↓                                                     │
│ POST /payment.php (action=release) OR                   │
│ POST /api/transaction-actions.php                       │
│   (transaction_uuid, action=release)                    │
│   ↓                                                     │
│ StatusMachine::requestRelease($txUuid, $userUuid)      │
│   ↓                                                     │
│ INSERT INTO transaction_intents                         │
│   (transaction_uuid, action, status)                    │
│   VALUES ($txUuid, 'RELEASE', 'pending')               │
└─────────────────────────────────────────────────────────┘

Python Side (cron job):
┌─────────────────────────────────────────────────────────┐
│ Cron runs every 2 minutes                               │
│   ↓                                                     │
│ SELECT * FROM transaction_intents WHERE status='pending'│
│   ↓                                                     │
│ For each intent:                                        │
│   ├─ Derive private key from mnemonic                  │
│   ├─ Sign transaction                                  │
│   ├─ Broadcast to blockchain                           │
│   ├─ UPDATE transaction_intents SET status='completed' │
│   └─ INSERT INTO transaction_statuses (RELEASED)       │
└─────────────────────────────────────────────────────────┘
```

**Benefits**:
- PHP never has access to private keys
- Blockchain operations are asynchronous
- Retry logic can be implemented in Python
- Web requests complete quickly (no blockchain wait)

**Trade-offs**:
- Eventual consistency (delay between intent and execution)
- Requires monitoring cron execution
- Failed intents need manual intervention

## Security Architecture

### Secret Separation

```
┌─────────────────────────────────────────────────────────┐
│                      .env file                          │
├─────────────────────────────────────────────────────────┤
│ DB_DRIVER=sqlite                                        │
│ DB_DSN=sqlite:db/store.sqlite                          │
│ SITE_URL=https://example.com                           │
│ SESSION_SALT=random123...                              │
│ COOKIE_ENCRYPTION_SALT=random456...                    │
│ CSRF_SALT=random789...                                 │
│ ─────────────────────────────────────────────────────── │
│ MNEMONIC=twelve word mnemonic phrase here...           │
│ ALCHEMY_API_KEY=abc123def456...                        │
│ COMMISSION_WALLET_MAINNET=0x...                        │
└─────────────────────────────────────────────────────────┘
         │                                    │
         │                                    │
         ▼                                    ▼
┌──────────────────────┐         ┌──────────────────────┐
│   PHP (Env.php)      │         │  Python (env.py)     │
│                      │         │                      │
│ Loads ONLY:          │         │ Loads ALL:           │
│ - DB_*               │         │ - DB_*               │
│ - SITE_*             │         │ - SITE_*             │
│ - *_SALT             │         │ - *_SALT             │
│                      │         │ - MNEMONIC           │
│ BLOCKS:              │         │ - ALCHEMY_*          │
│ - MNEMONIC           │         │ - COMMISSION_*       │
│ - ALCHEMY_*          │         │                      │
│ - COMMISSION_*       │         │                      │
└──────────────────────┘         └──────────────────────┘
```

**Attack Scenario**: If PHP is compromised via RCE vulnerability:
- Attacker can read `DB_*`, `SITE_*`, `*_SALT` variables
- Attacker **cannot** read `MNEMONIC` or `ALCHEMY_API_KEY`
- Funds remain safe (no private key access)
- Blockchain operations remain secure

### HD Wallet Security

```
Master Seed (MNEMONIC)
  ↓ BIP-39
Master Private Key
  ↓ BIP-32 Derivation
m/44'/60'/0'/0/{index}
  ↓
Transaction-Specific Private Key
  ↓
Escrow Address
```

**Security Properties**:
- Each transaction gets a unique address
- Addresses are deterministic (same UUID → same address)
- Private keys are derived on-demand, never stored
- Master seed is only in Python cron (never in PHP)
- Index is derived from transaction UUID hash

**Key Derivation**:
```python
def _derivation_index(transaction_uuid: str) -> int:
    h = hashlib.sha256(transaction_uuid.encode()).hexdigest()[:8]
    return int(h, 16) % (2**31)
```

## Database Architecture

### Portable Schema Design

The schema is designed to work on both SQLite and MariaDB/MySQL without changes.

**Techniques Used**:
1. **Conditional DDL**:
   ```php
   $pk = $this->sqlite 
       ? 'INTEGER PRIMARY KEY AUTOINCREMENT' 
       : 'INT AUTO_INCREMENT PRIMARY KEY';
   ```

2. **Portable Timestamps**:
   - Store as TEXT in `YYYY-MM-DD HH:MM:SS` format
   - PHP: `date('Y-m-d H:i:s')`
   - Python: `datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S')`

3. **Portable Upserts**:
   ```php
   // SQLite
   INSERT INTO config (key, value) VALUES (?, ?) 
   ON CONFLICT(key) DO UPDATE SET value = excluded.value
   
   // MariaDB
   REPLACE INTO config (key, value) VALUES (?, ?)
   ```

4. **No Database-Specific Features**:
   - No JSON columns (use TEXT with json_encode/decode)
   - No arrays (use separate tables)
   - No triggers or stored procedures
   - No full-text search (implement in application)

### View-Based Current State

Instead of maintaining "current status" columns that need updates, we use views:

```sql
-- Raw data (append-only)
transaction_statuses:
  id | transaction_uuid | time | status
  1  | abc123          | 12:00 | PENDING
  2  | abc123          | 12:05 | COMPLETED
  3  | def456          | 12:10 | PENDING

-- View (current status)
v_current_transaction_statuses:
  uuid   | current_status | updated_at
  abc123 | COMPLETED      | 12:05
  def456 | PENDING        | 12:10
```

**Benefits**:
- No update logic needed (append-only)
- Always consistent (view is computed)
- Complete history preserved

**Performance**:
- Views are fast for small-medium datasets (<100k transactions)
- For large datasets, consider materialized views or caching

## Scalability Considerations

### Current Limits

- **SQLite**: Good for up to ~100k transactions
- **Single Cron**: Can process ~1000 transactions/minute
- **PHP-FPM**: Depends on pool size (typically 50-100 concurrent requests)

### Scaling Strategies

#### Database Scaling

```
Phase 1: SQLite (0-10k transactions)
  ↓
Phase 2: MariaDB Single Instance (10k-1M transactions)
  ↓
Phase 3: MariaDB with Read Replicas (1M-10M transactions)
  ↓
Phase 4: Sharding by store_uuid (10M+ transactions)
```

#### Cron Scaling

```
Phase 1: Single Cron Instance
  ↓
Phase 2: Parallel Cron Workers (by chain_id)
  ↓
Phase 3: Task Queue (Celery/RQ) with Multiple Workers
  ↓
Phase 4: Dedicated Microservice for Blockchain Operations
```

#### Web Scaling

```
Phase 1: Single Server (Nginx + PHP-FPM)
  ↓
Phase 2: Load Balancer + Multiple Web Servers
  ↓
Phase 3: CDN for Static Assets
  ↓
Phase 4: API Gateway + Microservices
```

## Technology Choices

### Why PHP for Web Layer?

- **LEMP compatibility**: Standard stack, easy deployment
- **Simplicity**: One script per endpoint, no framework overhead
- **Maturity**: Stable, well-documented, widely supported
- **Security**: Separation from blockchain secrets

### Why Python for Blockchain Layer?

- **eth-account library**: Excellent HD wallet support
- **Ecosystem**: Rich blockchain tooling (web3.py, eth-utils)
- **Async support**: Future-proof for concurrent blockchain operations
- **Separation**: Runs as separate process, no web exposure

### Why SQLite for Development?

- **Zero configuration**: No server setup required
- **Portable**: Single file, easy to backup/restore
- **Fast**: Sufficient for development and testing
- **Compatible**: Same schema works on MariaDB

### Why MariaDB for Production?

- **Concurrent writes**: Better than SQLite for multi-user
- **Replication**: Built-in master-slave replication
- **Performance**: Optimized for web workloads
- **Compatibility**: Drop-in replacement for MySQL

### Why Alchemy API?

- **Reliability**: Enterprise-grade infrastructure
- **Features**: Balance queries, price feeds, webhooks
- **Free tier**: 300M compute units/month (sufficient for MVP)
- **Multi-chain**: Supports Ethereum, Polygon, Arbitrum, etc.

## Future Architecture Enhancements

### Planned Improvements

1. **Webhook Support**: Real-time transaction updates instead of polling
2. **Message Queue**: Replace cron with Celery/RQ for better reliability
3. **Caching Layer**: Redis for session storage and rate limiting
4. **API Gateway**: Kong/Tyk for centralized auth and rate limiting
5. **Monitoring**: Prometheus + Grafana for metrics
6. **Logging**: ELK stack for centralized logging

### Migration Path

```
Current: Monolithic PHP + Python Cron
  ↓
Phase 1: Extract Blockchain Service
  - Separate Python service with REST API
  - PHP calls service instead of writing intents
  ↓
Phase 2: Add Message Queue
  - Replace cron with Celery workers
  - Better retry logic and error handling
  ↓
Phase 3: Microservices
  - User Service
  - Store Service
  - Transaction Service
  - Blockchain Service
  ↓
Phase 4: Event-Driven Architecture
  - Event bus (Kafka/RabbitMQ)
  - Services communicate via events
```

---

**Document Version**: 1.0  
**Last Updated**: January 31, 2026
