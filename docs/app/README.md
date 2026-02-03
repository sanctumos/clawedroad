# Marketplace Application Documentation

**Version:** PHP/Python Hybrid (LEMP Stack)  
**Last Updated:** January 31, 2026

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Quick Start](#quick-start)
4. [Directory Structure](#directory-structure)
5. [Core Components](#core-components)
6. [API Reference](#api-reference)
7. [Database Schema](#database-schema)
8. [Security](#security)
9. [Deployment](#deployment)
10. [Troubleshooting](#troubleshooting)

---

## Overview

This is a cryptocurrency-based marketplace application built on a LEMP (Linux, Nginx, MariaDB/MySQL, PHP) stack with Python cron jobs for blockchain operations. The application supports:

- **Multi-store marketplace** with vendor and customer roles
- **EVM-based escrow payments** (Ethereum and compatible chains)
- **HD-derived escrow addresses** (BIP-32/44) for each transaction
- **Automated transaction lifecycle** management via Python cron
- **API key authentication** with rate limiting (60 req/min)
- **Session-based web authentication**
- **Admin configuration** for commission rates and timeouts
- **Dispute resolution** system
- **Referral payments** and multi-tier commission structure

### Integrating with agents (SDK & MCP)

- **Python SDK** ([sdk/](../../sdk/)) — Call the REST API from Python (API key or session). See [sdk/README.md](../../sdk/README.md).
- **SMCP plugin** ([smcp_plugin/marketplace/](../../smcp_plugin/marketplace/)) — Expose marketplace as MCP tools for any MCP-compatible agent.
- **Intro & SMCP server** — [docs/AGENTS-SDK-SMCP.md](../AGENTS-SDK-SMCP.md) explains how to run the official **Sanctum SMCP** server ([sanctumos/smcp](https://github.com/sanctumos/smcp)) with SSE or STDIO so agents can use the marketplace tools.

### Key Features

- **LEMP Design Philosophy**: URL path = file path. One PHP script per endpoint. No front controller for API/admin routes.
- **Portable Database**: Supports both SQLite (development) and MariaDB/MySQL (production)
- **Separation of Concerns**: PHP handles web requests; Python handles blockchain operations
- **Security First**: PHP never loads blockchain secrets (mnemonic, API keys)
- **Append-Only Status Machine**: Transaction statuses are immutable audit logs

---

## Architecture

### Technology Stack

**Web Layer (PHP 8.x)**
- Nginx web server
- PHP-FPM for script execution
- Session-based authentication for web UI
- API key authentication for programmatic access

**Database Layer**
- SQLite (development/testing)
- MariaDB/MySQL (production)
- Portable schema with views for current transaction states

**Blockchain Layer (Python 3.x)**
- HD wallet derivation (BIP-32/44)
- Alchemy API for balance checks and price feeds
- Automated escrow address generation
- Transaction lifecycle management

### Design Principles

1. **LEMP Pattern**: Direct file-to-URL mapping
   - `/api/stores.php` → `public/api/stores.php`
   - `/admin/config.php` → `public/admin/config.php`
   - No routing layer or front controller

2. **Separation of Secrets**
   - PHP loads: `DB_*`, `SITE_*`, `SESSION_SALT`, `COOKIE_ENCRYPTION_SALT`, `CSRF_SALT`
   - Python loads: `MNEMONIC`, `ALCHEMY_*`, `COMMISSION_WALLET_*`, `DB_*`
   - PHP **never** has access to blockchain secrets

3. **Append-Only Status Machine**
   - Transaction statuses are never updated, only appended
   - Views provide "current status" by selecting latest row
   - Complete audit trail for all state changes

4. **Intent-Based Blockchain Operations**
   - PHP writes "intent" records (RELEASE, CANCEL, PARTIAL_REFUND)
   - Python cron reads intents and performs blockchain operations
   - PHP never signs transactions or holds private keys

---

## Quick Start

### Prerequisites

- PHP 8.0+ with extensions: `pdo`, `pdo_sqlite` or `pdo_mysql`, `mbstring`, `json`
- Python 3.8+ with pip
- Nginx
- SQLite 3 or MariaDB/MySQL
- Alchemy API key (for blockchain operations)

### Installation

1. **Clone and Navigate**
   ```bash
   cd /path/to/store/app
   ```

2. **Configure Environment**
   ```bash
   cp .env.example .env
   # Edit .env with your settings
   ```

3. **Install Python Dependencies**
   ```bash
   pip install -r cron/requirements.txt
   ```

4. **Configure Nginx**
   ```bash
   cp nginx.conf.example /etc/nginx/sites-available/marketplace
   # Edit the file with your paths
   # Enable the site and reload Nginx
   ```

5. **Initialize Database**
   ```bash
   # Via CLI
   php public/schema.php
   
   # Or via HTTP
   curl http://localhost/schema.php
   ```

6. **Set Up Cron Job**
   ```bash
   # Add to crontab (runs every 2 minutes)
   */2 * * * * cd /path/to/store/app && python3 cron/cron.py >> /var/log/marketplace-cron.log 2>&1
   ```

### First Run

1. **Register Admin User** (via HTTP or direct DB insert)
   ```bash
   curl -X POST http://localhost/register.php \
     -d "username=admin&password=secure_password"
   ```

2. **Update User Role to Admin** (direct DB)
   ```bash
   sqlite3 db/store.sqlite "UPDATE users SET role='admin' WHERE username='admin';"
   ```

3. **Configure System Settings**
   ```bash
   curl -X POST http://localhost/admin/config.php \
     -H "Cookie: store_..." \
     -d "pending_duration=24h&completed_duration=336h"
   ```

---

## Directory Structure

```
app/
├── .env.example              # Environment template
├── nginx.conf.example        # Nginx configuration example
├── README.md                 # Points to docs/app/
│
│  (All docs: docs/app/ at workspace root)
│
├── cron/                     # Python blockchain automation
│   ├── cron.py              # Main cron entrypoint
│   ├── tasks.py             # Task implementations
│   ├── escrow.py            # HD wallet derivation
│   ├── alchemy_client.py    # Alchemy API client
│   ├── db.py                # Database connection
│   ├── env.py               # Environment loader
│   ├── requirements.txt     # Python dependencies
│   └── README.md            # Cron documentation
│
├── db/                       # Database files (SQLite)
│   ├── .gitkeep
│   └── store.sqlite         # SQLite database (not committed)
│
└── public/                   # Web document root
    ├── index.php            # Home page
    ├── login.php            # Login page
    ├── register.php         # Registration page
    ├── logout.php           # Logout handler
    ├── schema.php           # Database migration
    │
    ├── api/                 # Public/authenticated API endpoints
    │   ├── auth-user.php    # Get current API key user
    │   ├── deposits.php     # List deposits
    │   ├── disputes.php     # List disputes
    │   ├── items.php        # List/create items
    │   ├── keys.php         # List/create API keys
    │   ├── keys-revoke.php  # Revoke API key
    │   ├── stores.php       # List/create stores
    │   └── transactions.php # List/create transactions
    │
    ├── admin/               # Admin-only endpoints
    │   ├── config.php       # Get/set configuration
    │   ├── tokens.php       # List/add accepted tokens
    │   └── tokens-remove.php # Remove accepted token
    │
    └── includes/            # Shared PHP classes
        ├── bootstrap.php    # Common initialization
        ├── api_helpers.php  # Auth helper functions
        ├── ApiKey.php       # API key management
        ├── Config.php       # Configuration storage
        ├── Db.php           # Database abstraction
        ├── Env.php          # Environment loader
        ├── Schema.php       # Database schema
        ├── Session.php      # Session management
        ├── StatusMachine.php # Transaction status machine
        ├── User.php         # User management
        └── Views.php        # Database views
```

---

## Core Components

### PHP Classes

#### `Env` (includes/Env.php)
Loads environment variables from `.env` with security filtering.

**Security Feature**: Only loads PHP-relevant variables. Blocks Python-only secrets (MNEMONIC, ALCHEMY_API_KEY, COMMISSION_WALLET_*).

```php
Env::load($baseDir);
$dbDsn = Env::getRequired('DB_DSN');
$siteName = Env::get('SITE_NAME') ?? 'Marketplace';
```

#### `Db` (includes/Db.php)
Database abstraction layer supporting SQLite and MariaDB/MySQL.

```php
Db::init($baseDir);
$pdo = Db::pdo();
$isSqlite = Db::isSqlite();
```

#### `User` (includes/User.php)
User management with bcrypt password hashing.

**Roles**: `admin`, `staff`, `vendor`, `customer`

```php
$userRepo = new User($pdo);
$user = $userRepo->create($uuid, $username, $password, User::ROLE_CUSTOMER);
$user = $userRepo->verifyPassword($username, $password);
```

#### `Session` (includes/Session.php)
Session wrapper with salted session names.

```php
$session = new Session($baseDir);
$session->start();
$session->setUser($user);
$currentUser = $session->getUser();
$session->destroy();
```

#### `ApiKey` (includes/ApiKey.php)
API key management with rate limiting (60 requests/minute).

```php
$apiKeyRepo = new ApiKey($pdo);
$keyData = $apiKeyRepo->create($userUuid, 'My API Key');
$user = $apiKeyRepo->validate($apiKey);
$withinLimit = $apiKeyRepo->checkRateLimit($apiKeyId);
$apiKeyRepo->recordRequest($apiKeyId);
```

#### `Config` (includes/Config.php)
Admin-configurable settings storage.

**Default Settings**:
- `pending_duration`: 24h
- `completed_duration`: 336h (14 days)
- `stuck_duration`: 720h (30 days)
- `completion_tolerance`: 0.05 (5%)
- Commission rates: 2% (gold), 5% (silver), 10% (bronze), 20% (free)
- Referral percentages: 50% for all tiers

```php
$config = new Config($pdo);
$config->seedDefaults();
$duration = $config->get('pending_duration');
$tolerance = $config->getFloat('completion_tolerance', 0.05);
$config->set('pending_duration', '48h');
```

#### `StatusMachine` (includes/StatusMachine.php)
Append-only transaction status management.

**Statuses**: `PENDING`, `COMPLETED`, `RELEASED`, `FAILED`, `CANCELLED`, `FROZEN`

**Intents**: `RELEASE`, `CANCEL`, `PARTIAL_REFUND`

```php
$sm = new StatusMachine($pdo);

// Append status
$sm->appendTransactionStatus($txUuid, $amount, StatusMachine::STATUS_COMPLETED);

// Request blockchain action (Python cron will execute)
$sm->requestRelease($txUuid, $userUuid);
$sm->requestCancel($txUuid, $userUuid);
$sm->requestPartialRefund($txUuid, 0.5, $userUuid); // 50% refund

// Query current status
$current = $sm->getCurrentStatus($txUuid);
```

#### `Schema` (includes/Schema.php)
Database schema creation with portable SQL.

**Tables**: users, stores, store_users, items, item_categories, packages, package_prices, transactions, evm_transactions, transaction_statuses, shipping_statuses, payment_receipts, referral_payments, deposits, deposit_history, disputes, dispute_claims, transaction_intents, config, api_keys, api_key_requests, accepted_tokens

#### `Views` (includes/Views.php)
Database views for current transaction states.

**Views**:
- `v_transaction_statuses`: Min/max timestamps and statuses
- `v_shipping_statuses`: Latest shipping status per transaction
- `v_current_transaction_statuses`: Current state of all transactions
- `v_current_evm_transaction_statuses`: EVM transaction details
- `v_current_cumulative_transaction_statuses`: Complete transaction listing

### Python Modules

#### `cron.py`
Main cron entrypoint. Runs scheduled tasks:
1. Fill escrow addresses for new transactions
2. Poll PENDING transactions for completion
3. Fail old PENDING transactions
4. (Future) Release COMPLETED, freeze stuck, handle deposits

```bash
python cron/cron.py
```

#### `tasks.py`
Task implementations:
- `run_fill_escrow()`: Derive and assign escrow addresses
- `run_update_pending()`: Check balances and mark COMPLETED
- `run_fail_old_pending()`: Timeout old PENDING transactions

#### `escrow.py`
HD wallet derivation using BIP-32/44.

**Derivation Path**: `m/44'/60'/0'/0/{index}` where index = f(transaction_uuid)

```python
from escrow import derive_escrow_address
address = derive_escrow_address(mnemonic, transaction_uuid)
```

#### `alchemy_client.py`
Alchemy API client for:
- Balance queries (`eth_getBalance`)
- ETH/USD price feeds

```python
from alchemy_client import get_balance_wei, wei_to_eth, get_eth_usd_price
balance_wei = get_balance_wei(address, api_key, network)
balance_eth = wei_to_eth(balance_wei)
price_usd = get_eth_usd_price(api_key)
```

#### `db.py`
Database connection factory supporting SQLite and MariaDB/MySQL.

```python
from db import get_connection
conn = get_connection(base_dir)
```

#### `env.py`
Environment variable loader for Python.

```python
from env import load_dotenv, get, get_required
load_dotenv(base_dir)
mnemonic = get_required('MNEMONIC')
api_key = get('ALCHEMY_API_KEY', '')
```

---

## API Reference

### Authentication

**Session-based** (web UI):
- Login at `/login.php`
- Session cookie automatically included

**API Key** (programmatic):
- Create key at `/api/keys.php` (POST, requires session)
- Include in requests via:
  - Header: `Authorization: Bearer {key}`
  - Header: `X-API-Key: {key}`
  - Query: `?token={key}`

### Rate Limiting

- **60 requests per minute** per API key
- Returns `429 Too Many Requests` when exceeded

### Public Endpoints

#### GET /
Home page. Returns `OK`.

#### GET /api/stores.php
List all stores (public, no auth required).

**Response**:
```json
{
  "stores": [
    {
      "uuid": "abc123...",
      "storename": "MyStore",
      "description": "Store description",
      "vendorship_agreed_at": "2026-01-31 12:00:00",
      "created_at": "2026-01-31 12:00:00"
    }
  ]
}
```

#### GET /api/items.php
List items. Optional query param: `?store_uuid=...`

**Response**:
```json
{
  "items": [
    {
      "uuid": "def456...",
      "name": "Product Name",
      "description": "Product description",
      "store_uuid": "abc123...",
      "category_id": 1,
      "created_at": "2026-01-31 12:00:00"
    }
  ]
}
```

### Authenticated Endpoints (Session or API Key)

#### POST /register.php
Register new user.

**Parameters**:
- `username` (string, max 16 chars)
- `password` (string, min 8 chars)

**Response**: `Registered as {username}`

#### POST /login.php
Login user.

**Parameters**:
- `username` (string)
- `password` (string)

**Response**: `Logged in as {username}`

#### GET /logout.php
Destroy session.

**Response**: `Logged out`

#### POST /api/stores.php
Create store (requires session).

**Parameters**:
- `storename` (string, max 16 chars)
- `description` (string, optional)
- `vendorship_agree` (string, "1" to agree)

**Response**:
```json
{
  "ok": true,
  "uuid": "abc123..."
}
```

#### POST /api/items.php
Create item (requires session).

**Parameters**:
- `name` (string, required)
- `description` (string, optional)
- `store_uuid` (string, required)

**Response**:
```json
{
  "ok": true,
  "uuid": "def456..."
}
```

#### GET /api/transactions.php
List transactions (requires API key or session).

**Response**:
```json
{
  "transactions": [
    {
      "uuid": "tx123...",
      "type": "evm",
      "current_status": "PENDING",
      "current_amount": 0.0,
      "required_amount": 0.1,
      "escrow_address": "0x...",
      "chain_id": 1,
      "currency": "ETH",
      "buyer_username": "alice",
      "storename": "MyStore",
      "created_at": "2026-01-31 12:00:00",
      "updated_at": "2026-01-31 12:05:00"
    }
  ]
}
```

#### POST /api/transactions.php
Create transaction (requires session).

**Parameters**:
- `package_uuid` (string, required)
- `refund_address` (string, optional)
- `required_amount` (float, required)
- `chain_id` (int, default 1)
- `currency` (string, default "ETH")

**Response**:
```json
{
  "ok": true,
  "uuid": "tx123...",
  "escrow_address_pending": true
}
```

**Note**: Escrow address will be filled by Python cron within minutes.

#### GET /api/keys.php
List API keys for current user (requires session).

**Response**:
```json
{
  "keys": [
    {
      "id": 1,
      "name": "My API Key",
      "key_prefix": "abc12345",
      "created_at": "2026-01-31 12:00:00",
      "last_used_at": "2026-01-31 13:00:00"
    }
  ]
}
```

#### POST /api/keys.php
Create API key (requires session).

**Parameters**:
- `name` (string, optional)

**Response**:
```json
{
  "id": 1,
  "name": "My API Key",
  "key_prefix": "abc12345",
  "api_key": "abc12345def67890...",
  "created_at": "2026-01-31 12:00:00"
}
```

**Important**: Save the `api_key` value immediately. It cannot be retrieved later.

#### POST /api/keys-revoke.php
Revoke API key (requires session).

**Parameters**:
- `id` (int, required)

**Response**:
```json
{
  "ok": true
}
```

#### GET /api/auth-user.php
Get current user info (requires API key).

**Response**:
```json
{
  "username": "alice",
  "role": "customer",
  "user_uuid": "user123..."
}
```

#### GET /api/deposits.php
List deposits for current user's stores (requires session).

**Response**:
```json
{
  "deposits": [
    {
      "uuid": "dep123...",
      "store_uuid": "store123...",
      "currency": "USD",
      "crypto": "ETH",
      "address": "0x...",
      "crypto_value": 0.5,
      "fiat_value": 1000.0,
      "currency_rate": 2000.0,
      "created_at": "2026-01-31 12:00:00"
    }
  ]
}
```

#### GET /api/disputes.php
List disputes (requires session).

**Response**:
```json
{
  "disputes": [
    {
      "uuid": "dispute123...",
      "status": "open",
      "resolver_user_uuid": null,
      "created_at": "2026-01-31 12:00:00"
    }
  ]
}
```

### Admin Endpoints (Requires Admin Role)

#### GET /admin/config.php
Get configuration settings (requires admin session).

**Response**:
```json
{
  "pending_duration": "24h",
  "completed_duration": "336h",
  "stuck_duration": "720h",
  "completion_tolerance": "0.05",
  "partial_refund_resolver_percent": "0.10",
  "gold_account_commission": "0.02",
  "silver_account_commission": "0.05",
  "bronze_account_commission": "0.10",
  "free_account_commission": "0.20"
}
```

#### POST /admin/config.php
Update configuration settings (requires admin session).

**Parameters**: Any of the keys from GET response

**Response**:
```json
{
  "ok": true
}
```

#### GET /admin/tokens.php
List accepted tokens (requires admin session).

**Response**:
```json
{
  "tokens": [
    {
      "id": 1,
      "chain_id": 1,
      "symbol": "ETH",
      "contract_address": null,
      "created_at": "2026-01-31 12:00:00"
    }
  ]
}
```

#### POST /admin/tokens.php
Add accepted token (requires admin session).

**Parameters**:
- `chain_id` (int, required)
- `symbol` (string, required)
- `contract_address` (string, optional for native tokens)

**Response**:
```json
{
  "ok": true,
  "id": 2
}
```

#### POST /admin/tokens-remove.php
Remove accepted token (requires admin session).

**Parameters**:
- `id` (int, required)

**Response**:
```json
{
  "ok": true
}
```

---

## Database Schema

### Core Tables

#### users
User accounts with roles and referral tracking.

| Column | Type | Description |
|--------|------|-------------|
| uuid | TEXT PK | User unique identifier |
| username | TEXT UNIQUE | Username (max 16 chars) |
| passphrase_hash | TEXT | Bcrypt password hash |
| role | TEXT | admin, staff, vendor, customer |
| inviter_uuid | TEXT | Referrer user UUID |
| refund_address_evm | TEXT | Default refund address |
| resolver_evm_address | TEXT | Dispute resolver address |
| banned | INTEGER | 0 = active, 1 = banned |
| created_at | TEXT | Creation timestamp |
| updated_at | TEXT | Last update timestamp |
| deleted_at | TEXT | Soft delete timestamp |

#### stores
Vendor stores with tier flags.

| Column | Type | Description |
|--------|------|-------------|
| uuid | TEXT PK | Store unique identifier |
| storename | TEXT UNIQUE | Store name (max 16 chars) |
| description | TEXT | Store description |
| vendorship_agreed_at | TEXT | Terms agreement timestamp |
| is_gold | INTEGER | Gold tier flag |
| is_silver | INTEGER | Silver tier flag |
| is_bronze | INTEGER | Bronze tier flag |
| is_free | INTEGER | Free tier flag (default 1) |
| is_suspended | INTEGER | Suspension flag |
| created_at | TEXT | Creation timestamp |
| updated_at | TEXT | Last update timestamp |
| deleted_at | TEXT | Soft delete timestamp |

#### store_users
Many-to-many relationship between stores and users.

| Column | Type | Description |
|--------|------|-------------|
| store_uuid | TEXT PK | Store UUID |
| user_uuid | TEXT PK | User UUID |
| role | TEXT PK | owner, manager, staff |

#### items
Products listed in stores.

| Column | Type | Description |
|--------|------|-------------|
| uuid | TEXT PK | Item unique identifier |
| name | TEXT | Item name |
| description | TEXT | Item description |
| store_uuid | TEXT FK | Store UUID |
| category_id | INTEGER FK | Category ID |
| created_at | TEXT | Creation timestamp |
| updated_at | TEXT | Last update timestamp |
| deleted_at | TEXT | Soft delete timestamp |

#### packages
Purchasable packages/variants of items.

| Column | Type | Description |
|--------|------|-------------|
| uuid | TEXT PK | Package unique identifier |
| item_uuid | TEXT FK | Item UUID |
| store_uuid | TEXT FK | Store UUID |
| name | TEXT | Package name |
| description | TEXT | Package description |
| type | TEXT | Package type |
| created_at | TEXT | Creation timestamp |
| updated_at | TEXT | Last update timestamp |
| deleted_at | TEXT | Soft delete timestamp |

#### package_prices
Prices for packages in different currencies.

| Column | Type | Description |
|--------|------|-------------|
| uuid | TEXT PK | Price unique identifier |
| package_uuid | TEXT FK | Package UUID |
| currency | TEXT | Currency code (ETH, USDT, etc.) |
| price_usd | REAL | Price in USD |
| created_at | TEXT | Creation timestamp |

#### transactions
Core transaction records.

| Column | Type | Description |
|--------|------|-------------|
| uuid | TEXT PK | Transaction unique identifier |
| type | TEXT | Transaction type (evm, bitcoin) |
| description | TEXT | Transaction description |
| package_uuid | TEXT FK | Package UUID |
| store_uuid | TEXT FK | Store UUID |
| buyer_uuid | TEXT FK | Buyer user UUID |
| dispute_uuid | TEXT FK | Dispute UUID (if any) |
| refund_address | TEXT | Refund address override |
| created_at | TEXT | Creation timestamp |
| updated_at | TEXT | Last update timestamp |

#### evm_transactions
EVM-specific transaction data.

| Column | Type | Description |
|--------|------|-------------|
| uuid | TEXT PK FK | Transaction UUID |
| escrow_address | TEXT | HD-derived escrow address |
| amount | REAL | Required amount in crypto |
| chain_id | INTEGER | EVM chain ID (1=mainnet, 11155111=sepolia) |
| currency | TEXT | Currency symbol (ETH, USDT, etc.) |
| created_at | TEXT | Creation timestamp |
| updated_at | TEXT | Last update timestamp |

#### transaction_statuses
Append-only transaction status log.

| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER PK | Auto-increment ID |
| transaction_uuid | TEXT FK | Transaction UUID |
| time | TEXT | Status timestamp |
| amount | REAL | Amount at this status |
| status | TEXT | PENDING, COMPLETED, RELEASED, FAILED, CANCELLED, FROZEN |
| comment | TEXT | Status comment |
| user_uuid | TEXT FK | User who triggered status |
| payment_receipt_uuid | TEXT FK | Payment receipt UUID |
| created_at | TEXT | Record creation timestamp |

#### transaction_intents
Intent records for Python cron to execute.

| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER PK | Auto-increment ID |
| transaction_uuid | TEXT FK | Transaction UUID |
| action | TEXT | RELEASE, CANCEL, PARTIAL_REFUND |
| params | TEXT | JSON parameters |
| requested_at | TEXT | Request timestamp |
| requested_by_user_uuid | TEXT FK | Requesting user UUID |
| status | TEXT | pending, completed, failed |
| created_at | TEXT | Record creation timestamp |

#### shipping_statuses
Shipping status log.

| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER PK | Auto-increment ID |
| transaction_uuid | TEXT FK | Transaction UUID |
| time | TEXT | Status timestamp |
| status | TEXT | Shipping status |
| comment | TEXT | Status comment |
| user_uuid | TEXT FK | User who updated status |
| created_at | TEXT | Record creation timestamp |

#### payment_receipts
Serialized payment receipts (blockchain proofs).

| Column | Type | Description |
|--------|------|-------------|
| uuid | TEXT PK | Receipt unique identifier |
| type | TEXT | Receipt type |
| serialized_data | TEXT | JSON/serialized receipt data |
| version | INTEGER | Receipt format version |
| created_at | TEXT | Creation timestamp |

#### referral_payments
Referral commission tracking.

| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER PK | Auto-increment ID |
| transaction_uuid | TEXT FK | Transaction UUID |
| user_uuid | TEXT FK | Referrer user UUID |
| referral_percent | REAL | Referral percentage |
| referral_payment_eth | REAL | Payment in ETH |
| referral_payment_usd | REAL | Payment in USD |
| is_buyer_referral | INTEGER | 1 if buyer referral, 0 if vendor |
| created_at | TEXT | Creation timestamp |
| updated_at | TEXT | Last update timestamp |

#### deposits
Vendor deposit accounts.

| Column | Type | Description |
|--------|------|-------------|
| uuid | TEXT PK | Deposit unique identifier |
| store_uuid | TEXT FK | Store UUID |
| currency | TEXT | Fiat currency (USD, EUR, etc.) |
| crypto | TEXT | Crypto currency (ETH, BTC, etc.) |
| address | TEXT | Deposit address |
| crypto_value | REAL | Crypto balance |
| fiat_value | REAL | Fiat equivalent |
| currency_rate | REAL | Exchange rate |
| created_at | TEXT | Creation timestamp |
| updated_at | TEXT | Last update timestamp |
| deleted_at | TEXT | Soft delete timestamp |

#### deposit_history
Deposit transaction history.

| Column | Type | Description |
|--------|------|-------------|
| uuid | TEXT PK | History entry unique identifier |
| deposit_uuid | TEXT FK | Deposit UUID |
| action | TEXT | deposit, withdraw, adjust |
| value | REAL | Amount |
| created_at | TEXT | Creation timestamp |

#### disputes
Dispute records.

| Column | Type | Description |
|--------|------|-------------|
| uuid | TEXT PK | Dispute unique identifier |
| status | TEXT | open, resolved, cancelled |
| resolver_user_uuid | TEXT FK | Resolver user UUID |
| created_at | TEXT | Creation timestamp |
| updated_at | TEXT | Last update timestamp |
| deleted_at | TEXT | Soft delete timestamp |

#### dispute_claims
Claims within disputes.

| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER PK | Auto-increment ID |
| dispute_uuid | TEXT FK | Dispute UUID |
| claim | TEXT | Claim text |
| status | TEXT | pending, accepted, rejected |
| created_at | TEXT | Creation timestamp |
| updated_at | TEXT | Last update timestamp |

#### config
System configuration key-value store.

| Column | Type | Description |
|--------|------|-------------|
| key | TEXT PK | Configuration key |
| value | TEXT | Configuration value |

#### api_keys
API key storage.

| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER PK | Auto-increment ID |
| user_uuid | TEXT FK | User UUID |
| name | TEXT | Key name/label |
| api_key | TEXT | Full API key (64 hex chars) |
| key_prefix | TEXT | First 8 chars (for display) |
| created_at | TEXT | Creation timestamp |
| last_used_at | TEXT | Last use timestamp |
| expires_at | TEXT | Expiration timestamp |

#### api_key_requests
Rate limiting tracking.

| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER PK | Auto-increment ID |
| api_key_id | INTEGER FK | API key ID |
| requested_at | TEXT | Request timestamp |

#### accepted_tokens
Accepted payment tokens.

| Column | Type | Description |
|--------|------|-------------|
| id | INTEGER PK | Auto-increment ID |
| chain_id | INTEGER | EVM chain ID |
| symbol | TEXT | Token symbol |
| contract_address | TEXT | Token contract (null for native) |
| created_at | TEXT | Creation timestamp |

### Database Views

#### v_current_transaction_statuses
Current status of all transactions with store and buyer info.

**Columns**: uuid, description, type, package_uuid, store_uuid, buyer_uuid, dispute_uuid, current_status, current_amount, updated_at, created_at, current_shipping_status, number_of_messages, storename, buyer_username

#### v_current_evm_transaction_statuses
Current status of EVM transactions with escrow details.

**Columns**: All from `v_current_transaction_statuses` plus required_amount, escrow_address, chain_id, currency

#### v_current_cumulative_transaction_statuses
Comprehensive transaction listing (used by `/api/transactions.php`).

**Columns**: uuid, type, description, current_amount, current_status, current_shipping_status, number_of_messages, required_amount, escrow_address, chain_id, currency, buyer_username, storename, dispute_uuid, package_uuid, store_uuid, buyer_uuid, updated_at, created_at

---

## Security

### Environment Variable Separation

**Critical Security Feature**: PHP and Python load different subsets of `.env` to prevent secret exposure.

**PHP Loads**:
- `DB_DRIVER`, `DB_DSN`, `DB_USER`, `DB_PASSWORD`
- `SITE_URL`, `SITE_NAME`
- `SESSION_SALT`, `COOKIE_ENCRYPTION_SALT`, `CSRF_SALT`

**Python Loads**:
- All of the above DB variables
- `MNEMONIC` (HD wallet seed - **NEVER** loaded by PHP)
- `ALCHEMY_API_KEY` (blockchain API key - **NEVER** loaded by PHP)
- `ALCHEMY_NETWORK`
- `COMMISSION_WALLET_MAINNET`, `COMMISSION_WALLET_SEPOLIA`, `COMMISSION_WALLET_BASE`

**Why This Matters**: If PHP is compromised (e.g., via RCE vulnerability), the attacker cannot access the mnemonic or blockchain API keys. Only the Python cron process, which doesn't expose a web interface, has access to these secrets.

### Password Security

- **Bcrypt hashing** with cost factor 12
- Minimum password length: 8 characters
- Passwords never stored in plaintext
- No password reset mechanism (intentional MVP design)

### Session Security

- Session names are salted with `SESSION_SALT`
- File-based sessions by default (can be DB-backed)
- Session destruction on logout
- HTTP-only cookies recommended (configure in `php.ini`)

### API Key Security

- 64-character hexadecimal keys (256 bits of entropy)
- Stored in plaintext (MVP design - consider hashing for production)
- Rate limited to 60 requests/minute per key
- Keys inherit user role permissions
- Can be revoked by user

### Rate Limiting

- **60 requests per minute** per API key
- Tracked in `api_key_requests` table
- Old records pruned automatically (older than 2 minutes)
- Returns HTTP 429 when limit exceeded

### SQL Injection Prevention

- **All queries use prepared statements**
- PDO with parameter binding
- No string interpolation in SQL

### Input Validation

- Username: max 16 characters, unique
- Password: min 8 characters
- Store name: max 16 characters, unique
- All user inputs trimmed and validated

### Blockchain Security

- **HD wallet derivation**: Each transaction gets a unique address
- **Deterministic derivation**: Same transaction UUID always yields same address
- **No private key exposure**: Keys derived on-demand, never stored
- **Intent-based operations**: PHP never signs transactions

### Nginx Security Recommendations

```nginx
# Hide dot files
location ~ /\. {
    deny all;
}

# Disable PHP in uploads directory (if you add one)
location ~* /uploads/.*\.php$ {
    deny all;
}

# Security headers
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
```

---

## Deployment

### Production Checklist

1. **Environment Configuration**
   - [ ] Copy `.env.example` to `.env`
   - [ ] Generate strong random salts (SESSION_SALT, COOKIE_ENCRYPTION_SALT, CSRF_SALT)
   - [ ] Set SITE_URL and SITE_NAME
   - [ ] Configure database (MariaDB recommended for production)
   - [ ] Add MNEMONIC (generate new 12-word seed, **never reuse**)
   - [ ] Add ALCHEMY_API_KEY
   - [ ] Set ALCHEMY_NETWORK (mainnet for production)
   - [ ] Configure COMMISSION_WALLET_* addresses

2. **Database Setup**
   - [ ] Create database: `CREATE DATABASE store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
   - [ ] Create database user with appropriate permissions
   - [ ] Update `.env` with database credentials
   - [ ] Run schema: `php public/schema.php`
   - [ ] Verify tables created: `SHOW TABLES;`

3. **Web Server Configuration**
   - [ ] Copy and customize `nginx.conf.example`
   - [ ] Set correct document root (points to `public/`)
   - [ ] Configure PHP-FPM socket path
   - [ ] Set correct `env_path` in fastcgi_param
   - [ ] Enable HTTPS (use Let's Encrypt)
   - [ ] Test configuration: `nginx -t`
   - [ ] Reload Nginx: `systemctl reload nginx`

4. **PHP Configuration**
   - [ ] Set `session.cookie_httponly = 1`
   - [ ] Set `session.cookie_secure = 1` (HTTPS only)
   - [ ] Set `expose_php = Off`
   - [ ] Configure `upload_max_filesize` and `post_max_size` if needed
   - [ ] Set appropriate `memory_limit`

5. **Python Cron Setup**
   - [ ] Install Python dependencies: `pip install -r cron/requirements.txt`
   - [ ] Test cron manually: `python cron/cron.py`
   - [ ] Add to crontab: `*/2 * * * * cd /path/to/app && python3 cron/cron.py >> /var/log/marketplace-cron.log 2>&1`
   - [ ] Set up log rotation for cron log

6. **File Permissions**
   - [ ] Set ownership: `chown -R www-data:www-data /path/to/app`
   - [ ] Set directory permissions: `find /path/to/app -type d -exec chmod 755 {} \;`
   - [ ] Set file permissions: `find /path/to/app -type f -exec chmod 644 {} \;`
   - [ ] Make db/ writable: `chmod 775 /path/to/app/db`
   - [ ] Protect .env: `chmod 600 /path/to/app/.env`

7. **Security Hardening**
   - [ ] Disable directory listing in Nginx
   - [ ] Configure firewall (allow only 80, 443, SSH)
   - [ ] Set up fail2ban for SSH and HTTP
   - [ ] Enable automatic security updates
   - [ ] Configure backup strategy (database + .env)
   - [ ] Set up monitoring (uptime, disk space, cron execution)

8. **Initial Admin Setup**
   - [ ] Register first user via `/register.php`
   - [ ] Manually set role to admin in database
   - [ ] Login and configure system settings via `/admin/config.php`
   - [ ] Add accepted tokens via `/admin/tokens.php`

9. **Testing**
   - [ ] Test user registration and login
   - [ ] Test store creation
   - [ ] Test item creation
   - [ ] Test transaction creation
   - [ ] Verify escrow address generation (check cron logs)
   - [ ] Test API key creation and usage
   - [ ] Test rate limiting
   - [ ] Test admin endpoints

10. **Monitoring**
    - [ ] Set up application logging
    - [ ] Monitor cron execution (check for failures)
    - [ ] Monitor database size and performance
    - [ ] Set up alerts for critical errors
    - [ ] Monitor Alchemy API usage and rate limits

### Backup Strategy

**Critical Data**:
1. **Database**: Full backup daily, incremental hourly
2. **`.env` file**: Encrypted backup in secure location
3. **Mnemonic**: Paper backup in physical safe (never digital-only)

**Backup Commands**:
```bash
# SQLite backup
sqlite3 db/store.sqlite ".backup /backup/store-$(date +%Y%m%d-%H%M%S).sqlite"

# MariaDB backup
mysqldump -u user -p store > /backup/store-$(date +%Y%m%d-%H%M%S).sql

# .env backup (encrypt with GPG)
gpg --encrypt --recipient admin@example.com .env
```

### Scaling Considerations

**Database**:
- SQLite: Good for up to ~100k transactions
- MariaDB: Required for high-volume production
- Consider read replicas for reporting queries

**Cron**:
- Single cron instance is sufficient for most loads
- For high volume, consider task queue (Celery, RQ)
- Monitor cron execution time (should complete in <1 minute)

**Web Server**:
- PHP-FPM pool sizing: `pm.max_children` based on available RAM
- Nginx worker processes: 1 per CPU core
- Consider CDN for static assets (if added)

**Blockchain**:
- Alchemy free tier: 300M compute units/month
- Monitor usage in Alchemy dashboard
- Consider upgrading plan or adding fallback RPC providers

---

## Troubleshooting

### Common Issues

#### Database Connection Errors

**Symptom**: `PDOException: could not find driver`

**Solution**: Install PHP PDO extension
```bash
# Ubuntu/Debian
sudo apt-get install php-pdo php-sqlite3 php-mysql

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm
```

#### Escrow Address Not Generated

**Symptom**: `escrow_address` is NULL after several minutes

**Diagnosis**:
1. Check cron is running: `ps aux | grep cron.py`
2. Check cron logs: `tail -f /var/log/marketplace-cron.log`
3. Test cron manually: `python cron/cron.py`

**Common Causes**:
- Cron not scheduled in crontab
- Python dependencies not installed
- MNEMONIC not set in `.env`
- Database connection error

#### Transaction Stuck in PENDING

**Symptom**: Transaction stays PENDING despite funds sent

**Diagnosis**:
1. Check escrow address has funds: Use block explorer
2. Check Alchemy API key is valid
3. Check cron logs for balance check errors
4. Verify `completion_tolerance` setting (default 5%)

**Manual Fix**:
```sql
-- Check current status
SELECT * FROM v_current_evm_transaction_statuses WHERE uuid = 'tx_uuid';

-- Manually mark as COMPLETED (if funds confirmed on-chain)
INSERT INTO transaction_statuses (transaction_uuid, time, amount, status, comment, created_at)
VALUES ('tx_uuid', datetime('now'), 0.1, 'COMPLETED', 'Manual completion', datetime('now'));
```

#### API Rate Limit False Positives

**Symptom**: Rate limit errors despite low request volume

**Diagnosis**:
1. Check `api_key_requests` table: `SELECT COUNT(*) FROM api_key_requests WHERE api_key_id = X AND requested_at > datetime('now', '-1 minute');`
2. Old records may not be pruned

**Fix**:
```sql
-- Manually prune old records
DELETE FROM api_key_requests WHERE requested_at < datetime('now', '-2 minutes');
```

#### Session Not Persisting

**Symptom**: User logged out immediately after login

**Diagnosis**:
1. Check PHP session directory is writable: `ls -la /var/lib/php/sessions`
2. Check session cookie is set: Browser DevTools → Application → Cookies
3. Check `SESSION_SALT` is set in `.env`

**Fix**:
```bash
# Make session directory writable
sudo chown www-data:www-data /var/lib/php/sessions
sudo chmod 1733 /var/lib/php/sessions
```

#### Nginx 502 Bad Gateway

**Symptom**: Nginx returns 502 error

**Diagnosis**:
1. Check PHP-FPM is running: `systemctl status php8.1-fpm`
2. Check PHP-FPM socket path matches Nginx config
3. Check PHP-FPM error log: `/var/log/php8.1-fpm.log`

**Fix**:
```bash
# Restart PHP-FPM
sudo systemctl restart php8.1-fpm

# Check socket path
ls -la /run/php/php-fpm.sock
```

### Debug Mode

To enable verbose error reporting (**development only**):

**PHP** (add to `public/includes/bootstrap.php`):
```php
error_reporting(E_ALL);
ini_set('display_errors', '1');
```

**Python** (add to `cron/cron.py`):
```python
import logging
logging.basicConfig(level=logging.DEBUG)
```

> **⚠️ PRODUCTION WARNING**: Never enable `display_errors` in production! This exposes stack traces, file paths, and database details to attackers. The production PHP-FPM configuration in [DEPLOYMENT.md](DEPLOYMENT.md) uses `php_admin_flag[display_errors] = off` which cannot be overridden at runtime.

### Database Inspection

**SQLite**:
```bash
sqlite3 db/store.sqlite

# List tables
.tables

# Describe table
.schema users

# Query data
SELECT * FROM users;

# Exit
.quit
```

**MariaDB**:
```bash
mysql -u user -p store

# List tables
SHOW TABLES;

# Describe table
DESCRIBE users;

# Query data
SELECT * FROM users;

# Exit
EXIT;
```

### Log Locations

- **Nginx access**: `/var/log/nginx/access.log`
- **Nginx error**: `/var/log/nginx/error.log`
- **PHP-FPM**: `/var/log/php8.1-fpm.log`
- **Cron**: `/var/log/marketplace-cron.log` (custom location)
- **System cron**: `/var/log/syslog` (search for CRON)

---

## Additional Resources

### Related Documentation

- [INDEX.md](INDEX.md) - Full documentation index
- [REFERENCE.md](REFERENCE.md) - Quick reference guide
- [Cron README](../../app/cron/README.md) - Python cron detailed documentation
- [Nginx Example Config](../../app/nginx.conf.example) - Server configuration
- [Environment Template](../../app/.env.example) - Configuration reference

### External References

- [BIP-32](https://github.com/bitcoin/bips/blob/master/bip-0032.mediawiki) - Hierarchical Deterministic Wallets
- [BIP-44](https://github.com/bitcoin/bips/blob/master/bip-0044.mediawiki) - Multi-Account Hierarchy
- [Alchemy API Docs](https://docs.alchemy.com/) - Blockchain API documentation
- [PHP PDO Manual](https://www.php.net/manual/en/book.pdo.php) - Database abstraction
- [Nginx Documentation](https://nginx.org/en/docs/) - Web server configuration

### Support

For issues, questions, or contributions, please refer to the project repository.

---

**Document Version**: 1.0  
**Last Updated**: January 31, 2026  
**Maintainer**: Development Team
