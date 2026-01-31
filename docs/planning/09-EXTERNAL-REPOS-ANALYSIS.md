# External Repos Analysis (tmp/treasury, tmp/technonomicon.net)

This document summarizes findings from the cloned reference repositories in `tmp/` and how they inform the migration. **Binding decisions** remain in **08-PLANNING-DECISIONS-QA.md** (especially **8.9**).

**Important:** Use **tmp/treasury only for API logic** (Alchemy RPC, Web3, wallet signing). **Do not use treasuryaccounting or balance logic.** Use **Tochka (v1) code** for accounting and escrow balance/detection (see **01-ACCOUNTING-SPECIFICATION.md** and v1 reference).

---

## 1. tmp/treasury (Alchemy, EVM, Python)

**Source:** `tmp/treasury/` (cloned from lucidai-fun/treasury). The **root** README describes a different project (Lucid Telegram Bot). The EVM/Alchemy-relevant code lives in **treasury-core/** and some deployment/workers.

### 1.1 EVM / Base (Ethereum L2)

- **RPC client** (`treasury-core/chains/base/rpc.py`): Uses **Web3.py** with `HTTPProvider`. Methods: `get_balance`, `get_transaction_count`, `send_raw_transaction`, `get_transaction_receipt`, `get_latest_block_number`, `get_block(block_number, full_transactions=True)`, `get_transaction`, `get_transactions_for_address`, `get_incoming_eth_transfer_tx_hashes`. ERC-20 detection uses `Transfer(address,address,uint256)` topic: `ERC20_TRANSFER_TOPIC0 = "0x" + Web3.keccak(text="Transfer(address,address,uint256)").hex()` and `get_logs` with address topics. Sync Web3 calls are wrapped in `loop.run_in_executor()` for async use.
- **Wallet** (`treasury-core/chains/base/wallet.py`): **eth_account.Account.from_key(private_key)**; `get_address`, `get_balance(rpc)`, `build_transfer(to, amount_eth, rpc, gas_price_gwei?)`, `sign_and_send(raw_tx, rpc)`. Chain ID 8453 (Base Mainnet) hardcoded in tx dict. Gas: `estimate_gas` with 21000 fallback for simple ETH transfer.
- **Config** (`treasury-core/config.py`): Loads `.env` from treasury-core dir via `dotenv`. Uses single **ALCHEMY_API_KEY** to build RPC URLs: Solana `https://solana-mainnet.g.alchemy.com/v2/{key}`, Base `https://base-mainnet.g.alchemy.com/v2/{key}`. Fallback: `BASE_RPC_URL` if no Alchemy key. Base wallet: `BASE_OPERATIONS_ADDRESS`, `BASE_OPERATIONS_PRIVATE_KEY`. Gas: `base_gas_reserve_eth`, `base_ops_gas_buffer_eth`.
- **Price feed** (`treasury-core/utils/price_feed.py`): That repo uses Coinbase for ETH/USD (they buy through Coinbase). **We use Alchemy Prices API** (see 03): `GET /prices/v1/tokens/by-symbol?symbols=ETH` for ETH/USD; `POST .../tokens/by-address` for ERC-20. Optional fallback: CoinGecko (or similar) if Alchemy pricing is unavailable.

**Migration relevance:**  
- RPC URL pattern `https://{chain}.g.alchemy.com/v2/{ALCHEMY_API_KEY}` matches our plan (mainnet, Sepolia, Base).  
- Web3 + eth_account is a proven stack for EVM; Python cron can use the same libs (sync calls per run).  
- **Price:** We use **Alchemy Prices API** (by-symbol for ETH/USD, by-address for ERC-20); optional CoinGecko fallback (03). Treasury uses Coinbase only because that repo buys through Coinbase.

### 1.2 Main loop and deployment

- **Main loop** (`treasury-core/main.py`): **Long-running asyncio loop** (not cron): `TreasuryLoop.run()` with `poll_interval_seconds`, processes Solana + Base transactions, bridge, and **background queue workers** (`process_credit_queue_forever`, `process_diem_queue_forever`). Signal handlers (SIGTERM/SIGINT) for graceful shutdown.  
- **Deployment** (`deployment/treasury.service`): **systemd Type=simple**, `ExecStart=/usr/bin/python3 -m workers.treasury_service`, `Restart=always`, `EnvironmentFile=/opt/lucidai/.env`. So treasury runs as a **daemon**, not cron.

**Migration relevance:**  
- Our decision (08, 05) is **Python cron** (run, do work, exit). We do **not** adopt the long-running loop or systemd long-running service for MVP. Cron runs the same Python entrypoint periodically; no background workers inside one process.

### 1.3 Database (Python)

- **Queue DB** (`treasury-core/database.py`): **aiosqlite**, WAL mode. Tables: `base_inflow_ledger` (tx_hash UNIQUE, from/to address, amount_eth, amount_usd, block_number, timestamp), `bucket_balances`, `credit_queue`, `diem_queue` with status/locked_by/last_error. Idempotency by `tx_hash`; queue claim/release/complete/fail pattern.
- **Inflow detection**: Base side uses **balance-delta** (compare current balance to last seen); synthetic `tx_hash` for idempotency. **Do not use this logic.** Our escrow and balance/detection logic comes from **Tochka (v1)** and **01-ACCOUNTING-SPECIFICATION.md** only.

**Migration relevance:**  
- Use treasury only for **API patterns** (Web3, RPC, signing). Accounting and escrow balance/detection: **Tochka (v1)**. SQLite WAL + single-writer cron is compatible with our PHP + Python shared DB (PHP owns schema; Python writes only crypto tables).

### 1.4 .env.example (root)

- `ALCHEMY_API_KEY`, `TREASURY_DB_PATH`, `BASE_WALLET_PRIVATE_KEY`, optional `BILLING_*`, Venice/Coinbase-related vars. No HD mnemonic in this example (they use single Base wallet).

**Migration relevance:**  
- We add `ESCROW_MNEMONIC` (or similar) for HD-derived escrow; commission wallet per chain (03, 08).

---

## 2. tmp/technonomicon.net (PHP, LEMP, API keys)

**Source:** `tmp/technonomicon.net/` (cloned from technonomicon-lore/technonomicon.net). Plain PHP wiki/CMS with admin and REST API.

### 2.1 Structure

- **public/** — Document root: `index.php`, `admin/` (login, index, api-keys, change-password, edit, stats, upload-image), `api/` (create-article, delete-article, get-article, list-articles, update-article, search), `includes/` (auth, config, csrf, functions, seo), `wiki/`, `css/`, `js/`.
- **includes/config.php** — Defines `DB_PATH`, `SITE_URL`, `SESSION_NAME`, `SESSION_LIFETIME`, `PASSWORD_COST` (bcrypt). Development vs production via `HTTP_HOST` or `APP_ENV`. `getDbConnection()`: SQLite3, `busyTimeout`, `enableExceptions(true)`. `initializeDatabase()` creates tables (wiki_articles, admin_users, api_keys, api_rate_limits, stats_*). Default admin user created if none.
- **includes/auth.php** — Session-based auth: `isLoggedIn()`, `requireAuth()` (redirect to login), `login($username, $password)` (password_verify), `logout()`, `getCurrentUser()`, `changePassword()` (bcrypt PASSWORD_BCRYPT, cost from config). Table: `admin_users` (id, username, password_hash, created_at).

**Migration relevance:**  
- Mirrors our target: plain PHP, no framework; SQLite for dev; session auth; config in one place; DB init with schema creation. We’ll have marketplace tables and user/store roles; auth pattern (session + bcrypt) is reusable.

### 2.2 API keys

- **Schema** (`config.php`): `api_keys` (id, key_name, api_key UNIQUE, created_at, last_used).
- **functions.php**: `createApiKey($keyName)` — `bin2hex(random_bytes(32))` (64-char hex), insert; `validateApiKey($apiKey)` — lookup, update `last_used`, return true/false; `getApiKeyName($apiKey)`; `deleteApiKey($id)`; `getAllApiKeys()`.
- **admin/api-keys.php** — Require auth; POST create (with CSRF) and delete; display list with masked key and last_used. On create, the **raw key is shown once** ("Save this key now! It will not be shown again").
- **API usage** (`api/get-article.php`): Key from `HTTP_X_API_KEY` or `$_GET['api_key']`. If missing or invalid → 401 JSON. Then rate limit; then business logic; `recordApiUsage($endpoint, $isError)`.

**Migration relevance:**  
- Per our 04/06: API keys are **account-level** (we’ll tie to user_id and inherit role). Technonomicon keys are **global** (no user); we add `user_id` (and optionally key_name). Same pattern: random secret, store hash or plain (they store plain for validation); validate on each API request; optional last_used. We should decide: store **hash** of API key (validate with hash_equals) or plain (simpler, like technonomicon).

### 2.3 Rate limiting

- **Schema**: `api_rate_limits` (rate_key PRIMARY KEY, window_start, count).
- **functions.php**: `checkRateLimit($rateKey, $limit, $windowSeconds)` — sliding or fixed window in DB; returns true/false. API uses e.g. `$rateKey = 'get:' . $apiKey . ':' . $ip`; 60 req/60 sec.

**Migration relevance:**  
- We can reuse DB-backed rate limit per (api_key, endpoint) or per (api_key, ip) for MVP instead of Redis.

### 2.4 CSRF

- **includes/csrf.php**: Session-stored token; `generateCsrfToken()`, `getCsrfToken()`, `verifyCsrfToken($token)`, `requireCsrfToken()` (die on POST if invalid), `csrfField()` for forms.
- Admin forms (e.g. api-keys create/delete) use POST + hidden `csrf_token` and `requireCsrfToken()`.

**Migration relevance:**  
- Use the same pattern for all PHP admin/form POSTs (vendorship agree, settings, etc.).

### 2.5 API response pattern

- JSON: `header('Content-Type: application/json')`, `http_response_code(401|404|405|429)`, `json_encode(['success' => false, 'error' => '...'])` or success payload. Method check (GET only for get-article). Record usage and errors for stats.

**Migration relevance:**  
- Same style for our PHP API (agents): JSON, clear status codes, success/error shape.

### 2.6 Database

- SQLite only in technonomicon; path `__DIR__ . '/../../db/wiki.db'`. No MariaDB in this repo. Our plan: SQLite MVP, MariaDB prod — we’ll need a thin abstraction or env-driven DSN so the same PHP code works with both (05).

---

## 3. Summary Table

| Area            | Treasury (Python)                    | Technonomicon (PHP)                    | Migration use |
|-----------------|--------------------------------------|----------------------------------------|----------------|
| EVM RPC         | Web3, Alchemy URL by chain           | —                                      | Python cron uses Web3 + Alchemy URLs per chain |
| Wallet / sign   | eth_account, private key             | —                                      | Python: HD from mnemonic for escrow; commission wallet from .env |
| Price           | Coinbase (that repo only)            | —                                      | **Alchemy Prices API** (by-symbol, by-address); optional CoinGecko (03). |
| Process model   | Long-running systemd daemon          | —                                      | We use **cron** only (08.9). |
| DB (Python)     | aiosqlite, WAL, queue tables         | —                                      | Python: shared DB with PHP; cron = reference only. |
| Auth            | —                                    | Session, bcrypt, requireAuth           | PHP auth pattern; add roles/stores |
| API keys        | —                                    | Create/validate/delete, X-API-KEY      | Add user_id, role; **plain** in MVP (08.9); hashed on roadmap |
| Rate limit      | —                                    | DB table, per key+ip                   | **Per API key**, 60/min default (08.9); roadmap: pay for higher |
| CSRF            | —                                    | Session token, requireCsrfToken        | All admin POSTs |
| Config          | .env, dotenv                         | config.php (constants, DB path)        | PHP: load **only relevant** .env vars (08.9); Python: .env for secrets |

---

## 4. Decisions (from 08.9)

- **ETH/USD price**: Alchemy **Prices API** (by-symbol for ETH, by-address for ERC-20); optional CoinGecko fallback (03). Treasury uses Coinbase for that repo only.
- **API key storage**: Plain in MVP; hashed on roadmap.
- **Rate limit**: Per API key, 60/min default; roadmap: pay for higher access.
- **.env in PHP**: Load only relevant vars; shared with Python but no unnecessary secrets in PHP.
- **Treasury vs Tochka**: Treasury = **API logic only**. Accounting and escrow balance/detection = **Tochka (v1)**.
- **Python**: Cron only; no long-running daemon.

*(Previous "New questions" 1–7 were answered and recorded in 08.9.)*

’s 

