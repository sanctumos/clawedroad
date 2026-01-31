# Database Schema Documentation

Complete reference for the Marketplace database schema, including tables, views, relationships, and queries.

## Table of Contents

1. [Overview](#overview)
2. [Entity Relationship Diagram](#entity-relationship-diagram)
3. [Table Reference](#table-reference)
4. [View Reference](#view-reference)
5. [Common Queries](#common-queries)
6. [Indexes](#indexes)
7. [Data Types](#data-types)
8. [Migration Guide](#migration-guide)

---

## Overview

### Design Principles

1. **Portable Schema**: Works on both SQLite and MariaDB/MySQL without changes
2. **Append-Only Status**: Transaction statuses are never updated, only appended
3. **UUID Primary Keys**: For distributed systems and security
4. **Soft Deletes**: Records marked as deleted rather than removed
5. **Timestamp Tracking**: All entities have created_at, updated_at, deleted_at

### Database Support

- **SQLite 3.x**: Development and small deployments (<100k transactions)
- **MariaDB 10.6+**: Production deployments
- **MySQL 8.0+**: Alternative to MariaDB

---

## Entity Relationship Diagram

```
users ──────────┬─────────────┐
  │             │             │
  │ (buyer)     │ (inviter)   │ (resolver)
  │             │             │
  ▼             ▼             ▼
transactions  users       disputes
  │             │             │
  │             └─────────────┘
  │
  ├─── evm_transactions (1:1)
  │      │
  │      └─── escrow_address (derived from mnemonic)
  │
  ├─── transaction_statuses (1:N, append-only)
  │      │
  │      └─── payment_receipts (N:1)
  │
  ├─── shipping_statuses (1:N)
  │
  ├─── transaction_intents (1:N)
  │
  └─── referral_payments (1:N)

stores ─────────┬─────────────┐
  │             │             │
  ├─── items   │             │
  │      │      │             │
  │      └─── packages        │
  │             │             │
  │             └─── package_prices
  │
  └─── store_users (M:N with users)

deposits ───── stores
  │
  └─── deposit_history (1:N)

disputes ───── transactions
  │
  └─── dispute_claims (1:N)

api_keys ───── users
  │
  └─── api_key_requests (1:N, rate limiting)

accepted_tokens (system config)
config (system config)
item_categories (hierarchical)
```

---

## Table Reference

### users

User accounts with authentication and role management.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| uuid | TEXT | PRIMARY KEY | User unique identifier (32 hex chars) |
| username | TEXT | NOT NULL, UNIQUE | Username (max 16 chars) |
| passphrase_hash | TEXT | NOT NULL | Bcrypt password hash (cost 12) |
| role | TEXT | NOT NULL | admin, staff, vendor, customer |
| inviter_uuid | TEXT | FK users.uuid | Referrer user UUID |
| refund_address_evm | TEXT | NULL | Default EVM refund address |
| resolver_evm_address | TEXT | NULL | Dispute resolver payout address |
| banned | INTEGER | NOT NULL, DEFAULT 0 | 0 = active, 1 = banned |
| created_at | TEXT | NOT NULL | Creation timestamp |
| updated_at | TEXT | NULL | Last update timestamp |
| deleted_at | TEXT | NULL | Soft delete timestamp |

**Indexes** (MariaDB only):
- `idx_users_username` on `username`
- `idx_users_role` on `role`
- `idx_users_inviter` on `inviter_uuid`

**Example**:
```sql
INSERT INTO users (uuid, username, passphrase_hash, role, banned, created_at)
VALUES ('abc123def456...', 'alice', '$2y$12$...', 'customer', 0, '2026-01-31 12:00:00');
```

---

### stores

Vendor stores with tier management.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| uuid | TEXT | PRIMARY KEY | Store unique identifier |
| storename | TEXT | NOT NULL, UNIQUE | Store name (max 16 chars) |
| description | TEXT | NULL | Store description |
| vendorship_agreed_at | TEXT | NULL | Terms agreement timestamp |
| is_gold | INTEGER | NOT NULL, DEFAULT 0 | Gold tier flag (2% commission) |
| is_silver | INTEGER | NOT NULL, DEFAULT 0 | Silver tier flag (5% commission) |
| is_bronze | INTEGER | NOT NULL, DEFAULT 0 | Bronze tier flag (10% commission) |
| is_free | INTEGER | NOT NULL, DEFAULT 1 | Free tier flag (20% commission) |
| is_suspended | INTEGER | NOT NULL, DEFAULT 0 | Suspension flag |
| created_at | TEXT | NOT NULL | Creation timestamp |
| updated_at | TEXT | NULL | Last update timestamp |
| deleted_at | TEXT | NULL | Soft delete timestamp |

**Indexes** (MariaDB only):
- `idx_stores_storename` on `storename`

**Commission Tiers**:
- **Gold**: 2% commission, 50% referral share
- **Silver**: 5% commission, 50% referral share
- **Bronze**: 10% commission, 50% referral share
- **Free**: 20% commission, 50% referral share

---

### store_users

Many-to-many relationship between stores and users.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| store_uuid | TEXT | PRIMARY KEY, FK stores.uuid | Store UUID |
| user_uuid | TEXT | PRIMARY KEY, FK users.uuid | User UUID |
| role | TEXT | PRIMARY KEY | owner, manager, staff |

**Example**:
```sql
-- Assign user as store owner
INSERT INTO store_users (store_uuid, user_uuid, role)
VALUES ('store123', 'user456', 'owner');
```

---

### items

Products listed in stores.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| uuid | TEXT | PRIMARY KEY | Item unique identifier |
| name | TEXT | NOT NULL | Item name |
| description | TEXT | NULL | Item description |
| store_uuid | TEXT | NOT NULL, FK stores.uuid | Store UUID |
| category_id | INTEGER | NULL, FK item_categories.id | Category ID |
| created_at | TEXT | NOT NULL | Creation timestamp |
| updated_at | TEXT | NULL | Last update timestamp |
| deleted_at | TEXT | NULL | Soft delete timestamp |

**Indexes** (MariaDB only):
- `idx_items_store` on `store_uuid`

---

### item_categories

Hierarchical item categories.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INTEGER | PRIMARY KEY AUTOINCREMENT | Category ID |
| name_en | TEXT | NOT NULL | Category name (English) |
| parent_id | INTEGER | NULL, FK item_categories.id | Parent category ID |

**Example**:
```sql
-- Root category
INSERT INTO item_categories (name_en, parent_id) VALUES ('Electronics', NULL);

-- Subcategory
INSERT INTO item_categories (name_en, parent_id) VALUES ('Laptops', 1);
```

---

### packages

Purchasable packages/variants of items.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| uuid | TEXT | PRIMARY KEY | Package unique identifier |
| item_uuid | TEXT | NOT NULL, FK items.uuid | Item UUID |
| store_uuid | TEXT | NOT NULL, FK stores.uuid | Store UUID |
| name | TEXT | NULL | Package name (e.g., "Standard", "Premium") |
| description | TEXT | NULL | Package description |
| type | TEXT | NULL | Package type |
| created_at | TEXT | NOT NULL | Creation timestamp |
| updated_at | TEXT | NULL | Last update timestamp |
| deleted_at | TEXT | NULL | Soft delete timestamp |

**Indexes** (MariaDB only):
- `idx_packages_item` on `item_uuid`
- `idx_packages_store` on `store_uuid`

---

### package_prices

Prices for packages in different currencies.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| uuid | TEXT | PRIMARY KEY | Price unique identifier |
| package_uuid | TEXT | NOT NULL, FK packages.uuid | Package UUID |
| currency | TEXT | NOT NULL | Currency code (ETH, USDT, etc.) |
| price_usd | REAL | NOT NULL | Price in USD |
| created_at | TEXT | NOT NULL | Creation timestamp |

**Example**:
```sql
-- ETH price
INSERT INTO package_prices (uuid, package_uuid, currency, price_usd, created_at)
VALUES ('price123', 'pkg456', 'ETH', 200.00, '2026-01-31 12:00:00');
```

---

### transactions

Core transaction records.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| uuid | TEXT | PRIMARY KEY | Transaction unique identifier |
| type | TEXT | NOT NULL | Transaction type (evm, bitcoin) |
| description | TEXT | NULL | Transaction description |
| package_uuid | TEXT | NOT NULL, FK packages.uuid | Package UUID |
| store_uuid | TEXT | NOT NULL, FK stores.uuid | Store UUID |
| buyer_uuid | TEXT | NOT NULL, FK users.uuid | Buyer user UUID |
| dispute_uuid | TEXT | NULL, FK disputes.uuid | Dispute UUID (if any) |
| refund_address | TEXT | NULL | Refund address override |
| created_at | TEXT | NOT NULL | Creation timestamp |
| updated_at | TEXT | NULL | Last update timestamp |

**Indexes** (MariaDB only):
- `idx_transactions_store` on `store_uuid`
- `idx_transactions_buyer` on `buyer_uuid`
- `idx_transactions_dispute` on `dispute_uuid`

---

### evm_transactions

EVM-specific transaction data (1:1 with transactions).

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| uuid | TEXT | PRIMARY KEY, FK transactions.uuid | Transaction UUID |
| escrow_address | TEXT | NULL | HD-derived escrow address (filled by cron) |
| amount | REAL | NOT NULL | Required amount in crypto |
| chain_id | INTEGER | NOT NULL | EVM chain ID (1=mainnet, 11155111=sepolia) |
| currency | TEXT | NOT NULL | Currency symbol (ETH, USDT, etc.) |
| created_at | TEXT | NOT NULL | Creation timestamp |
| updated_at | TEXT | NULL | Last update timestamp |

**Chain IDs**:
- `1`: Ethereum Mainnet
- `11155111`: Sepolia Testnet
- `8453`: Base
- `137`: Polygon
- `42161`: Arbitrum One

**Example**:
```sql
-- Create EVM transaction
INSERT INTO evm_transactions (uuid, escrow_address, amount, chain_id, currency, created_at)
VALUES ('tx123', NULL, 0.1, 1, 'ETH', '2026-01-31 12:00:00');

-- Escrow address filled by Python cron
UPDATE evm_transactions 
SET escrow_address = '0x1234567890abcdef1234567890abcdef12345678', 
    updated_at = '2026-01-31 12:01:00'
WHERE uuid = 'tx123';
```

---

### transaction_statuses

Append-only transaction status log.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INTEGER | PRIMARY KEY AUTOINCREMENT | Auto-increment ID |
| transaction_uuid | TEXT | NOT NULL, FK transactions.uuid | Transaction UUID |
| time | TEXT | NOT NULL | Status timestamp |
| amount | REAL | NOT NULL | Amount at this status |
| status | TEXT | NOT NULL | Status code (see below) |
| comment | TEXT | NULL | Status comment |
| user_uuid | TEXT | NULL, FK users.uuid | User who triggered status |
| payment_receipt_uuid | TEXT | NULL, FK payment_receipts.uuid | Payment receipt UUID |
| created_at | TEXT | NULL | Record creation timestamp |

**Indexes** (MariaDB only):
- `idx_tx_statuses_tx` on `transaction_uuid`
- `idx_tx_statuses_status` on `status`

**Status Codes**:
- `PENDING`: Awaiting payment
- `COMPLETED`: Payment received
- `RELEASED`: Funds released to vendor
- `FAILED`: Transaction failed (timeout, insufficient funds)
- `CANCELLED`: Transaction cancelled (refunded)
- `FROZEN`: Transaction frozen (dispute or investigation)

**Important**: This table is **append-only**. Never UPDATE or DELETE rows. Always INSERT new rows.

**Example**:
```sql
-- Initial status (created by Python cron)
INSERT INTO transaction_statuses (transaction_uuid, time, amount, status, comment, created_at)
VALUES ('tx123', '2026-01-31 12:00:00', 0.0, 'PENDING', 'Escrow address created', '2026-01-31 12:00:00');

-- Payment received (created by Python cron)
INSERT INTO transaction_statuses (transaction_uuid, time, amount, status, comment, created_at)
VALUES ('tx123', '2026-01-31 12:05:00', 0.1, 'COMPLETED', 'Transaction funded', '2026-01-31 12:05:00');

-- Funds released (created by Python cron after intent)
INSERT INTO transaction_statuses (transaction_uuid, time, amount, status, comment, created_at)
VALUES ('tx123', '2026-01-31 13:00:00', 0.1, 'RELEASED', 'Funds released to vendor', '2026-01-31 13:00:00');
```

---

### transaction_intents

Intent records for Python cron to execute.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INTEGER | PRIMARY KEY AUTOINCREMENT | Auto-increment ID |
| transaction_uuid | TEXT | NOT NULL, FK transactions.uuid | Transaction UUID |
| action | TEXT | NOT NULL | Action code (see below) |
| params | TEXT | NULL | JSON parameters |
| requested_at | TEXT | NOT NULL | Request timestamp |
| requested_by_user_uuid | TEXT | NULL, FK users.uuid | Requesting user UUID |
| status | TEXT | NOT NULL, DEFAULT 'pending' | pending, completed, failed |
| created_at | TEXT | NULL | Record creation timestamp |

**Indexes** (MariaDB only):
- `idx_intents_tx` on `transaction_uuid`
- `idx_intents_status` on `status`

**Action Codes**:
- `RELEASE`: Release funds to vendor
- `CANCEL`: Cancel transaction and refund buyer
- `PARTIAL_REFUND`: Partial refund (params: `{"refund_percent": 0.5}`)

**Example**:
```sql
-- PHP writes intent
INSERT INTO transaction_intents (transaction_uuid, action, params, requested_at, requested_by_user_uuid, status, created_at)
VALUES ('tx123', 'RELEASE', NULL, '2026-01-31 13:00:00', 'user456', 'pending', '2026-01-31 13:00:00');

-- Python cron updates after execution
UPDATE transaction_intents SET status = 'completed' WHERE id = 1;
```

---

### shipping_statuses

Shipping status log.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INTEGER | PRIMARY KEY AUTOINCREMENT | Auto-increment ID |
| transaction_uuid | TEXT | NOT NULL, FK transactions.uuid | Transaction UUID |
| time | TEXT | NOT NULL | Status timestamp |
| status | TEXT | NOT NULL | Shipping status |
| comment | TEXT | NULL | Status comment |
| user_uuid | TEXT | NULL, FK users.uuid | User who updated status |
| created_at | TEXT | NULL | Record creation timestamp |

**Indexes** (MariaDB only):
- `idx_shipping_statuses_tx` on `transaction_uuid`

**Example**:
```sql
INSERT INTO shipping_statuses (transaction_uuid, time, status, comment, user_uuid, created_at)
VALUES ('tx123', '2026-01-31 14:00:00', 'DISPATCHED', 'Tracking: 1Z999AA10123456784', 'vendor123', '2026-01-31 14:00:00');
```

---

### payment_receipts

Serialized payment receipts (blockchain proofs).

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| uuid | TEXT | PRIMARY KEY | Receipt unique identifier |
| type | TEXT | NOT NULL | Receipt type (evm_tx, bitcoin_tx) |
| serialized_data | TEXT | NOT NULL | JSON/serialized receipt data |
| version | INTEGER | NOT NULL, DEFAULT 0 | Receipt format version |
| created_at | TEXT | NOT NULL | Creation timestamp |

**Example**:
```sql
INSERT INTO payment_receipts (uuid, type, serialized_data, version, created_at)
VALUES (
  'receipt123',
  'evm_tx',
  '{"tx_hash":"0xabc...","block_number":12345678,"from":"0x...","to":"0x...","value":"0.1"}',
  0,
  '2026-01-31 12:05:00'
);
```

---

### referral_payments

Referral commission tracking.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INTEGER | PRIMARY KEY AUTOINCREMENT | Auto-increment ID |
| transaction_uuid | TEXT | NOT NULL, FK transactions.uuid | Transaction UUID |
| user_uuid | TEXT | NOT NULL, FK users.uuid | Referrer user UUID |
| referral_percent | REAL | NOT NULL | Referral percentage (0.5 = 50%) |
| referral_payment_eth | REAL | NOT NULL, DEFAULT 0 | Payment in ETH |
| referral_payment_usd | REAL | NOT NULL, DEFAULT 0 | Payment in USD |
| is_buyer_referral | INTEGER | NOT NULL | 1 if buyer referral, 0 if vendor |
| created_at | TEXT | NOT NULL | Creation timestamp |
| updated_at | TEXT | NULL | Last update timestamp |

**Indexes** (MariaDB only):
- `idx_referral_tx` on `transaction_uuid`

**Example**:
```sql
-- Buyer referral (50% of commission)
INSERT INTO referral_payments (transaction_uuid, user_uuid, referral_percent, referral_payment_eth, referral_payment_usd, is_buyer_referral, created_at)
VALUES ('tx123', 'referrer456', 0.5, 0.01, 20.00, 1, '2026-01-31 13:00:00');
```

---

### deposits

Vendor deposit accounts.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| uuid | TEXT | PRIMARY KEY | Deposit unique identifier |
| store_uuid | TEXT | NOT NULL, FK stores.uuid | Store UUID |
| currency | TEXT | NOT NULL | Fiat currency (USD, EUR, etc.) |
| crypto | TEXT | NOT NULL | Crypto currency (ETH, BTC, etc.) |
| address | TEXT | NOT NULL | Deposit address |
| crypto_value | REAL | NOT NULL | Crypto balance |
| fiat_value | REAL | NOT NULL | Fiat equivalent |
| currency_rate | REAL | NOT NULL | Exchange rate |
| created_at | TEXT | NOT NULL | Creation timestamp |
| updated_at | TEXT | NULL | Last update timestamp |
| deleted_at | TEXT | NULL | Soft delete timestamp |

**Indexes** (MariaDB only):
- `idx_deposits_store` on `store_uuid`

---

### deposit_history

Deposit transaction history.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| uuid | TEXT | PRIMARY KEY | History entry unique identifier |
| deposit_uuid | TEXT | NOT NULL, FK deposits.uuid | Deposit UUID |
| action | TEXT | NOT NULL | deposit, withdraw, adjust |
| value | REAL | NOT NULL | Amount |
| created_at | TEXT | NOT NULL | Creation timestamp |

---

### disputes

Dispute records.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| uuid | TEXT | PRIMARY KEY | Dispute unique identifier |
| status | TEXT | NOT NULL | open, resolved, cancelled |
| resolver_user_uuid | TEXT | NULL, FK users.uuid | Resolver user UUID |
| created_at | TEXT | NOT NULL | Creation timestamp |
| updated_at | TEXT | NULL | Last update timestamp |
| deleted_at | TEXT | NULL | Soft delete timestamp |

**Indexes** (MariaDB only):
- `idx_disputes_status` on `status`

---

### dispute_claims

Claims within disputes.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INTEGER | PRIMARY KEY AUTOINCREMENT | Auto-increment ID |
| dispute_uuid | TEXT | NOT NULL, FK disputes.uuid | Dispute UUID |
| claim | TEXT | NOT NULL | Claim text |
| status | TEXT | NOT NULL | pending, accepted, rejected |
| created_at | TEXT | NOT NULL | Creation timestamp |
| updated_at | TEXT | NULL | Last update timestamp |

---

### config

System configuration key-value store.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| key | TEXT | PRIMARY KEY | Configuration key |
| value | TEXT | NOT NULL | Configuration value |

**Default Configuration**:

| Key | Default Value | Description |
|-----|---------------|-------------|
| pending_duration | 24h | Time before PENDING transactions fail |
| completed_duration | 336h | Time before COMPLETED transactions auto-release |
| stuck_duration | 720h | Time before transactions freeze for investigation |
| completion_tolerance | 0.05 | Payment tolerance (5%) |
| partial_refund_resolver_percent | 0.10 | Resolver fee for partial refunds (10%) |
| gold_account_commission | 0.02 | Gold tier commission (2%) |
| silver_account_commission | 0.05 | Silver tier commission (5%) |
| bronze_account_commission | 0.10 | Bronze tier commission (10%) |
| free_account_commission | 0.20 | Free tier commission (20%) |
| gold_account_referral_percent | 0.50 | Gold tier referral share (50%) |
| silver_account_referral_percent | 0.50 | Silver tier referral share (50%) |
| bronze_account_referral_percent | 0.50 | Bronze tier referral share (50%) |
| free_account_referral_percent | 0.50 | Free tier referral share (50%) |
| android_developer_username | | Android developer username |
| android_developer_commission | 0 | Android developer commission |

---

### api_keys

API key storage.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INTEGER | PRIMARY KEY AUTOINCREMENT | Auto-increment ID |
| user_uuid | TEXT | NOT NULL, FK users.uuid | User UUID |
| name | TEXT | NULL | Key name/label |
| api_key | TEXT | NOT NULL | Full API key (64 hex chars) |
| key_prefix | TEXT | NOT NULL | First 8 chars (for display) |
| created_at | TEXT | NOT NULL | Creation timestamp |
| last_used_at | TEXT | NULL | Last use timestamp |
| expires_at | TEXT | NULL | Expiration timestamp |

**Indexes** (MariaDB only):
- `idx_api_keys_user` on `user_uuid`
- `idx_api_keys_key` on `api_key(64)`

---

### api_key_requests

Rate limiting tracking.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INTEGER | PRIMARY KEY AUTOINCREMENT | Auto-increment ID |
| api_key_id | INTEGER | NOT NULL, FK api_keys.id | API key ID |
| requested_at | TEXT | NOT NULL | Request timestamp |

**Indexes** (MariaDB only):
- `idx_api_key_requests_key` on `api_key_id`
- `idx_api_key_requests_at` on `requested_at`

---

### accepted_tokens

Accepted payment tokens.

**Columns**:

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INTEGER | PRIMARY KEY AUTOINCREMENT | Auto-increment ID |
| chain_id | INTEGER | NOT NULL | EVM chain ID |
| symbol | TEXT | NOT NULL | Token symbol |
| contract_address | TEXT | NULL | Token contract (null for native) |
| created_at | TEXT | NOT NULL | Creation timestamp |

**Unique Constraint**: `(chain_id, contract_address)`

**Example**:
```sql
-- Native token (ETH)
INSERT INTO accepted_tokens (chain_id, symbol, contract_address, created_at)
VALUES (1, 'ETH', NULL, '2026-01-31 12:00:00');

-- ERC-20 token (USDT)
INSERT INTO accepted_tokens (chain_id, symbol, contract_address, created_at)
VALUES (1, 'USDT', '0xdac17f958d2ee523a2206206994597c13d831ec7', '2026-01-31 12:00:00');
```

---

## View Reference

### v_transaction_statuses

Aggregated transaction status information.

**Columns**:
- `transaction_uuid`: Transaction UUID
- `max_timestamp`: Latest status timestamp
- `min_timestamp`: Earliest status timestamp
- `min_amount`: Amount at first status
- `max_amount`: Amount at latest status
- `min_status`: First status
- `max_status`: Current status

---

### v_shipping_statuses

Latest shipping status per transaction.

**Columns**:
- `transaction_uuid`: Transaction UUID
- `max_timestamp`: Latest shipping status timestamp
- `max_status`: Current shipping status

---

### v_current_transaction_statuses

Current status of all transactions with store and buyer info.

**Columns**:
- `uuid`: Transaction UUID
- `description`: Transaction description
- `type`: Transaction type (evm, bitcoin)
- `package_uuid`: Package UUID
- `store_uuid`: Store UUID
- `buyer_uuid`: Buyer UUID
- `dispute_uuid`: Dispute UUID (if any)
- `current_status`: Current transaction status
- `current_amount`: Current amount
- `updated_at`: Last status update
- `created_at`: Transaction creation time
- `current_shipping_status`: Current shipping status
- `number_of_messages`: Message count (placeholder)
- `storename`: Store name
- `buyer_username`: Buyer username

---

### v_current_evm_transaction_statuses

Current status of EVM transactions with escrow details.

**Columns**: All from `v_current_transaction_statuses` plus:
- `required_amount`: Required payment amount
- `escrow_address`: HD-derived escrow address
- `chain_id`: EVM chain ID
- `currency`: Currency symbol

---

### v_current_cumulative_transaction_statuses

Comprehensive transaction listing (used by API).

**Columns**: All from `v_current_evm_transaction_statuses`

---

## Common Queries

### Transaction Queries

**Get current transaction status**:
```sql
SELECT * FROM v_current_cumulative_transaction_statuses
WHERE uuid = 'tx123';
```

**List pending transactions**:
```sql
SELECT uuid, escrow_address, required_amount, current_amount, created_at
FROM v_current_evm_transaction_statuses
WHERE current_status = 'PENDING'
ORDER BY created_at DESC;
```

**List transactions by store**:
```sql
SELECT * FROM v_current_cumulative_transaction_statuses
WHERE store_uuid = 'store123'
ORDER BY created_at DESC;
```

**List transactions by buyer**:
```sql
SELECT * FROM v_current_cumulative_transaction_statuses
WHERE buyer_uuid = 'buyer123'
ORDER BY created_at DESC;
```

**Transaction status history**:
```sql
SELECT time, status, amount, comment
FROM transaction_statuses
WHERE transaction_uuid = 'tx123'
ORDER BY time ASC;
```

### User Queries

**Find user by username**:
```sql
SELECT * FROM users
WHERE username = 'alice' AND deleted_at IS NULL;
```

**List user's stores**:
```sql
SELECT s.*
FROM stores s
JOIN store_users su ON s.uuid = su.store_uuid
WHERE su.user_uuid = 'user123' AND s.deleted_at IS NULL;
```

**List user's API keys**:
```sql
SELECT id, name, key_prefix, created_at, last_used_at
FROM api_keys
WHERE user_uuid = 'user123'
ORDER BY created_at DESC;
```

### Store Queries

**List all active stores**:
```sql
SELECT uuid, storename, description, created_at
FROM stores
WHERE deleted_at IS NULL
ORDER BY storename;
```

**List store items**:
```sql
SELECT * FROM items
WHERE store_uuid = 'store123' AND deleted_at IS NULL
ORDER BY created_at DESC;
```

**Store transaction stats**:
```sql
SELECT 
    current_status,
    COUNT(*) AS count,
    SUM(current_amount) AS total_amount
FROM v_current_cumulative_transaction_statuses
WHERE store_uuid = 'store123'
GROUP BY current_status;
```

### Admin Queries

**System statistics**:
```sql
SELECT 
    (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL) AS total_users,
    (SELECT COUNT(*) FROM stores WHERE deleted_at IS NULL) AS total_stores,
    (SELECT COUNT(*) FROM transactions) AS total_transactions,
    (SELECT COUNT(*) FROM v_current_cumulative_transaction_statuses WHERE current_status = 'PENDING') AS pending_transactions,
    (SELECT COUNT(*) FROM v_current_cumulative_transaction_statuses WHERE current_status = 'COMPLETED') AS completed_transactions;
```

**Recent transactions**:
```sql
SELECT uuid, buyer_username, storename, current_status, current_amount, created_at
FROM v_current_cumulative_transaction_statuses
ORDER BY created_at DESC
LIMIT 50;
```

**Configuration**:
```sql
SELECT * FROM config ORDER BY key;
```

---

## Indexes

### SQLite

SQLite automatically creates indexes for PRIMARY KEY and UNIQUE constraints. Additional indexes are not created in SQLite to keep the schema simple.

### MariaDB/MySQL

Additional indexes are created for foreign keys and frequently queried columns:

```sql
-- Users
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_inviter ON users(inviter_uuid);

-- Stores
CREATE INDEX idx_stores_storename ON stores(storename);

-- Items
CREATE INDEX idx_items_store ON items(store_uuid);

-- Packages
CREATE INDEX idx_packages_item ON packages(item_uuid);
CREATE INDEX idx_packages_store ON packages(store_uuid);

-- Transactions
CREATE INDEX idx_transactions_store ON transactions(store_uuid);
CREATE INDEX idx_transactions_buyer ON transactions(buyer_uuid);
CREATE INDEX idx_transactions_dispute ON transactions(dispute_uuid);

-- Transaction Statuses
CREATE INDEX idx_tx_statuses_tx ON transaction_statuses(transaction_uuid);
CREATE INDEX idx_tx_statuses_status ON transaction_statuses(status);

-- Transaction Intents
CREATE INDEX idx_intents_tx ON transaction_intents(transaction_uuid);
CREATE INDEX idx_intents_status ON transaction_intents(status);

-- Shipping Statuses
CREATE INDEX idx_shipping_statuses_tx ON shipping_statuses(transaction_uuid);

-- Referral Payments
CREATE INDEX idx_referral_tx ON referral_payments(transaction_uuid);

-- Deposits
CREATE INDEX idx_deposits_store ON deposits(store_uuid);

-- Disputes
CREATE INDEX idx_disputes_status ON disputes(status);

-- API Keys
CREATE INDEX idx_api_keys_user ON api_keys(user_uuid);
CREATE INDEX idx_api_keys_key ON api_keys(api_key(64));

-- API Key Requests
CREATE INDEX idx_api_key_requests_key ON api_key_requests(api_key_id);
CREATE INDEX idx_api_key_requests_at ON api_key_requests(requested_at);
```

---

## Data Types

### TEXT vs VARCHAR

We use `TEXT` for all string columns to maintain portability between SQLite and MariaDB/MySQL. In MariaDB, `TEXT` is equivalent to `VARCHAR(65535)`.

### INTEGER vs BIGINT

We use `INTEGER` for all integer columns. SQLite treats all integers as 64-bit. In MariaDB, `INTEGER` is equivalent to `INT` (32-bit), which is sufficient for our use case.

### REAL vs DECIMAL

We use `REAL` for all floating-point columns. SQLite uses 64-bit IEEE floating point. In MariaDB, `REAL` is equivalent to `DOUBLE`.

**Note**: For financial calculations, consider using integer arithmetic (store values in smallest unit, e.g., wei for ETH).

### Timestamps

All timestamps are stored as `TEXT` in `YYYY-MM-DD HH:MM:SS` format (ISO 8601 without timezone).

**PHP**:
```php
$now = date('Y-m-d H:i:s');
```

**Python**:
```python
from datetime import datetime
now = datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S')
```

**SQL**:
```sql
-- SQLite
datetime('now')

-- MariaDB
NOW()
```

---

## Migration Guide

### SQLite to MariaDB

1. **Export Data**:
```bash
sqlite3 db/store.sqlite .dump > dump.sql
```

2. **Clean Dump File**:
```bash
# Remove SQLite-specific commands
sed -i '/PRAGMA/d' dump.sql
sed -i '/BEGIN TRANSACTION/d' dump.sql
sed -i '/COMMIT/d' dump.sql
```

3. **Create MariaDB Database**:
```sql
CREATE DATABASE marketplace CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

4. **Run Schema**:
```bash
php public/schema.php
```

5. **Import Data**:
```bash
mysql -u marketplace_user -p marketplace < dump.sql
```

### Schema Updates

To add new columns or tables:

1. **Update Schema.php**:
```php
private function createNewTable(): void
{
    $pk = $this->pk();
    $this->exec("CREATE TABLE IF NOT EXISTS new_table (
        id {$pk},
        name TEXT NOT NULL,
        created_at TEXT NOT NULL
    )");
}
```

2. **Add to run() method**:
```php
public function run(): void
{
    // ... existing tables
    $this->createNewTable();
}
```

3. **Run Migration**:
```bash
php public/schema.php
```

**Note**: `CREATE TABLE IF NOT EXISTS` ensures idempotency.

---

**Document Version**: 1.0  
**Last Updated**: January 31, 2026
