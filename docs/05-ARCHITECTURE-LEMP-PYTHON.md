# Architecture: LEMP + Python Cron

**Binding decisions:** See **08-PLANNING-DECISIONS-QA.md** (plain PHP, Python = cron, DB = SQLite MVP / MariaDB prod, .env secrets, single-tenant).

## 1. Target Stack

- **Website**: **Plain PHP** (no framework) on **LEMP** (Linux, Nginx, PHP). Serves all user-facing pages, forms, and the **public API** (primarily for **agents**). **DB**: **SQLite** for MVP, **MariaDB** for prod. **Sessions**: PHP-owned (file or DB), not Redis. K.I.S.S.
- **Crypto**: **Python** runs as **cron** (scheduled job; runs, does work, exits). **Internal only** (no public HTTP). Handles EVM: HD-derived escrow addresses, balance checks, sends (release/cancel/partial refund, deposit withdraw). **No “fund from user wallet” in MVP** (08: buyer sends from external wallet only). Uses **Alchemy API** for chain access. **Cron only** — no long-running process.

## 2. Boundaries

### 2.1 PHP (LEMP)

- **Owns**: Users, sessions, stores, items, packages, transactions (metadata and status), disputes, messages, support tickets, API keys, config.
- **Does**: Auth (U/P, sessions, API key validation), CRUD, listing, search, all HTTP endpoints (web + API). For crypto actions (release, cancel, etc.), PHP writes **intent/state** to DB (e.g. pending_action); **Python cron** picks it up—PHP does **not** call Python (no internal API in MVP).
- **Does not**: Hold private keys; does not sign or broadcast EVM transactions; does not call Alchemy. Does not invoke Python; contract is **DB only**.

### 2.2 Python (Cron)

- **Owns**: **Single mnemonic** (in .env); **HD-derived** escrow keys; Alchemy client; signing and sending logic.
- **Does**: On each **cron run**: generate escrow addresses (derived); get escrow and vendor-deposit balances (ETH + token); send ETH/token (release, cancel, partial refund, deposit withdraw). **Does not** handle in-app buyer wallets or "fund from user wallet" in MVP (08: buyer pays from external wallet only). Writes status and receipts to DB. Exits when done.
- **Does not**: Expose HTTP to the internet; does not own user/session/auth. Runs on schedule and exits (no long-running process).

### 2.3 Database (SQLite / MariaDB, .env configurable)

- **Shared**: Single DB for PHP and Python. **Write for both** SQLite and MariaDB; **.env configurable** which driver (e.g. `DB_DRIVER=sqlite` vs `mariadb`). **Dev**: SQLite. **Prod**: MariaDB. Postgres is not used. PHP owns schema and migrations; Python only reads/writes tables needed for crypto.
- **Views**: Re-implement equivalent of current Postgres views in **portable SQL** (SQLite and MariaDB compatible). Avoid Postgres-specific features (e.g. `interval`, materialized views) or use conditional DDL.

## 3. Contracts Between PHP and Python

### 3.1 Option A: DB as Contract

- **PHP**: Inserts/updates “intent” rows (e.g. `transaction_release_requests`: transaction_uuid, requested_at, status = pending). Updates transaction status and receipt when done.
- **Python**: Polls (or listens) for pending intents; performs chain action; writes result (tx hash, success/fail) and updates intent status; PHP (or Python) updates `transaction_statuses` and `payment_receipts`.

### 3.2 Option B: Internal API (PHP → Python) — **Not used in MVP**

- **PHP**: Would call internal HTTP or queue (Redis, RabbitMQ). **We do not use this.** Contract is DB only (08).
- **Python**: No HTTP server; cron only. Option B is out of scope for MVP.

### 3.3 Option C: Python Cron Polls DB (Chosen)

- **Python**: **Cron** (not long-running): each run: “find PENDING transactions, poll escrow balance via Alchemy; if funded, mark COMPLETED”; “find COMPLETED older than completed_duration, run release”; “find FAILED/CANCELLED/RELEASED with non-zero amount, reconcile”; etc. Writes status and receipts directly to DB.
- **PHP**: Can “request” release/cancel (e.g. set “pending_action”); Python cron picks it up. PHP does not sign or send; Python is sole writer of crypto results.

**Decision (08):** Python = **cron**, not long-running process. **Option C** (DB as contract). Option B (internal API) and Redis/queue are **not** in MVP.

## 4. Cron (No Async Loop, No User Wallets)

- **Current (Go)**: gocron runs transaction tasks (update pending, fail old, release old, freeze, cancel, reconcile) and **escrow/deposit** balance updates; plus currency rates, SERP, messageboard, stats.
- **Target**: **Python cron** (scheduled, e.g. every 1–5 min) runs **crypto-related** tasks only: update pending, fail old pending, release old completed, freeze stuck, cancel not dispatched, reconcile released/cancelled; **update escrow and vendor-deposit balances** (no in-app buyer wallets in MVP). **PHP** cron can run **non-crypto** tasks: currency rates, search index, stats, notifications. **No Redis, no queue, no async loop** — cron runs, does work, exits.

## 5. Security

- **Keys**: Only in Python process (or HSM/KMS). Never in PHP or DB in plaintext.
- **Python**: No HTTP server; **cron only**. Not exposed to internet. Does not listen on any port.
- **DB**: PHP and Python use same DB; use least-privilege DB user for Python (only tables needed for crypto).
- **API**: Public API on PHP; validate API key or session; PHP **writes intent/state to DB**; Python cron reads DB, performs crypto, writes results. PHP does not call Python; no internal API.

## 6. Deployment Sketch

- **Nginx**: Reverse proxy; PHP-FPM for `.php`; static assets from filesystem.
- **DB**: **SQLite** (MVP, single file) or **MariaDB** (prod, single instance); schema and views portable.
- **Python**: **Cron** (e.g. systemd timer or crontab) every 1–10 minutes; reads .env for mnemonic and Alchemy API key; runs, updates DB, exits.
- **Secrets**: **.env** only (no vault in MVP). **Single-tenant** deployment. **PHP**: Load **only relevant** .env vars (e.g. DB_*, SITE_*, session/cookie); do not load Python-only secrets (mnemonic, Alchemy key) into PHP to avoid exposure (08.9). .env is shared between PHP and Python; each side reads only what it needs.
- **No Redis** for MVP (PHP sessions = file/DB; no queue).

## 7. Files to Mirror (Logic Only)

- **Crypto tasks**: `modules/marketplace/tasks_transaction.go`, `tasks_wallet.go` → Python (update pending, fail old, release old, freeze, reconcile; **escrow and vendor-deposit** balances only — no user/buyer wallets in MVP).
- **Crypto models**: `models_transaction_cc_ethereum.go`, `models_wallet_ethereum.go`, `models_receipt.go` → Python (release/cancel/partial refund logic; receipt creation).
- **API layer (Payaka)**: `modules/apis/payments_ethereum.go` → Python using Alchemy (balance, send).

All accounting rules (status machine, percents, invariants) stay as in **01-ACCOUNTING-SPECIFICATION.md**; only the “who runs it” (Python) and “how chain is called” (Alchemy) change.
