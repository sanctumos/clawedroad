# Changelog

All notable changes to Clawed Road are documented here.

**Versioning:** Clawed Road is **v2**—a new line, not a minor bump on Tochka. Calling it v1.something would've been dishonest: different stack, different roadmap. We're still in dev; **2.0.0** will be the first stable. Until then, pre-releases use **minor version bumps** (2.0.0-dev, 2.1.0-dev, …). The original stack (Tochka Free Market, Go/Postgres/Redis) lives in `v1/` as reference and will stay once we ship a stable tested release.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [2.5.2-dev] - 2026-02-03

### Fixed

- **api/stores.php, api/transactions.php, book.php** — Use `User::generateUuid()` instead of `$userRepo->generateUuid()` (static method called as instance method, causes deprecation/fatal on PHP 8+). Fixes issue #2.

---

## [2.5.1-dev] - 2026-01-31

### Added

- **V2.5 build (Phases 1–11)** — Schema: `password_reset_tokens`, `invite_codes`, `reviews`, `store_warnings`, `support_tickets`, `support_ticket_messages`, `private_messages`, `deposit_withdraw_intents`, `audit_log`; `stores.withdraw_address`, `transactions.buyer_confirmed_at`, `disputes.transaction_uuid`, `dispute_claims.user_uuid`; `recovery_rate_limit`, `login_rate_limit`. Nav: Settings, Referrals, My orders, Support, vendor (My store, Add item, Deposits), Staff.
- **User & settings** — Public profile `user.php?username=…`; `settings/user.php` (change password), `settings/store.php` (store name, description, withdraw address, vendorship re-agree); `referrals.php` (referral link, referred users, earnings); `verification/agreement.php`, `verification/plan.php`.
- **Auth** — `recover.php` (password recovery, token shown in UI, 5/hr per IP, CSRF); register with optional `?invite=CODE`; `admin/users.php` (list users, ban, grant staff, grant seller; admin-only, CSRF, AuditLog); login rate limit 10 attempts / 5 min per IP.
- **Vendor CMS** — `deposits.php`, `deposits/add.php`, `deposits/withdraw.php` (owner-only, `to_address` from store); `item/edit.php` (edit, soft-delete; store membership). Python cron: fill deposit addresses, update balances, process withdrawal intents.
- **Store & reviews** — `store.php` tabs: Items, Reviews, Warnings; staff resolve/acknowledge warnings; `review/add.php` for buyers after RELEASED.
- **Transactions** — `payment.php` action buttons and POST handlers per state/permission matrix (mark shipped, release, cancel, confirm received, open dispute).
- **Disputes** — `dispute/new.php` (start dispute, link tx, FROZEN); `dispute.php` (detail, add claim; staff resolve/partial refund; AuditLog); `staff/disputes.php`.
- **Support** — `support.php`, `support/new.php` (5 tickets/hr), `support/ticket.php` (thread, reply, 20 msgs/hr; staff set status; AuditLog).
- **Private messages** — `messages.php` (conversations, thread, send; 10 msgs/min, body ≤10k; CSRF).
- **Staff dashboard** — `staff/index.php` (staff/admin); `staff/stores.php`, `staff/tickets.php`, `staff/disputes.php`, `staff/warnings.php`, `staff/deposits.php`, `staff/stats.php`, `staff/categories.php` (CRUD item_categories).
- **E2E full-site coverage** — Anonymous redirects for all auth-required pages; customer 200 for referrals, support, deposits, messages, settings, verification/agreement; dispute/review/item/edit/support-ticket paths (302, 404); customer 403 on staff and admin/users (via seeded `e2e_customer`); admin 200 for admin/users and all staff pages. **RecoverE2ETest**, **UserProfileE2ETest**. **121 E2E tests** (Unit + Integration + E2E).

### Changed

- **E2E runner** — Request JSON may include `app_dir` (absolute path); runner sets `MARKETPLACE_APP_DIR` so child process uses test `.env` and test DB. In test mode, `REMOTE_ADDR` varied per request to avoid login/recovery rate limits. `Env::load()` uses `MARKETPLACE_APP_DIR` when set.
- **E2E helpers** — `E2ETestCase::extractCsrfFromBody()` for CSRF from HTML; `runRequest()` adds `app_dir` from `TEST_BASE_DIR`. Register E2E: GET form then POST with CSRF and cookies; error tests accept "Invalid request" when CSRF missing.
- **Test bootstrap** — Seeds non-admin user `e2e_customer` / `password123` for E2E customer-403 tests.

### Fixed

- **referrals.php** — Removed extra `)` that caused parse error (line 22).
- **admin/users.php** — Initialize `$postSubjectUuid`; guard `$targetUuid` / `$targetUsername` before `findByUuid` / `findByUsername` to avoid null and fatal.

---

## [2.3.0-dev] - 2026-01-31

### Added

- **E2E coverage at all user levels** — Full-stack E2E tests (`app/tests/E2E/FullStackE2ETest.php`) for anonymous, customer (session), vendor (store + item), and admin: public pages and API 401s when unauthenticated; payments, create-store, API keys/stores/items/transactions/deposits/disputes with session; admin dashboard, config, tokens with admin session; customer → admin config 403; book/payment 404 for invalid ids. **149 tests** (Unit + Integration + E2E).
- **Session auth in E2E runner** — `app/tests/run_request.php` accepts `cookies` in the request JSON and adds `session_name` / `session_id` to the response when a session is active (so login/register work in CLI where `headers_list()` is empty). `E2ETestCase::loginAs()` and `parseCookiesFromResponse()` for session-based flows.
- **Admin user seed** — Optional `ADMIN_USERNAME` / `ADMIN_PASSWORD` in `.env`; schema and test bootstrap create or update that user as admin for dev/demo and E2E.
- **Admin dashboard (HTML)** — `app/public/admin/index.php`: config table and accepted tokens list; admin-only; redirects to login when not authenticated.
- **Create store (vendor) page** — `app/public/create-store.php` and form: storename, description, vendorship agreement; session required; redirects to store on success. Header links: "Create store" when logged in, "Admin" when role is admin.

### Changed

- **E2E expectations** — Index and logout assert 302 (no Location in CLI). Login and register accept `session_name`/`session_id` from response when Set-Cookie is unavailable. Register success asserts 302.
- **Test bootstrap** — Seeds admin user after schema/config so E2E can log in as admin. Test `.env` includes `ADMIN_USERNAME` and `ADMIN_PASSWORD`. `Env` allows `ADMIN_USERNAME` / `ADMIN_PASSWORD`.

### Fixed

- **payments.php / payment.php** — Use view column `updated_at` for ORDER BY and `uuid` for WHERE (not `max_timestamp` / `transaction_uuid`); fixes 500 when viewing My orders.
- **admin/index.php** — Cast `chain_id` and `symbol` to string before `htmlspecialchars()` (SQLite can return int).
- **api/items.php** — Use `User::generateUuid()` instead of `$userRepo->generateUuid()`.

---

## [2.2.0-dev] - 2026-01-31

### Added

- **Python SDK** — `sdk/` package (`marketplace-sdk`) for the Marketplace REST API: API key and session auth, all endpoints (health, stores, items, transactions, keys, deposits, disputes, admin config/tokens). Typed exceptions (ValidationError, UnauthorizedError, RateLimitError, etc.). Install: `pip install -e sdk`. See [sdk/README.md](sdk/README.md).
- **SMCP plugin** — `smcp_plugin/marketplace/` MCP plugin exposing marketplace as tools (e.g. `marketplace__list-stores`, `marketplace__create-transaction`). Commands: health, list-stores, list-items, get-auth-user, list-transactions, create-store, create-item, create-transaction, list-keys, create-key, revoke-key, list-deposits, list-disputes. Uses SDK; installable into Sanctum SMCP `plugins/`. See [smcp_plugin/marketplace/README.md](smcp_plugin/marketplace/README.md) and [INSTALL.md](smcp_plugin/marketplace/INSTALL.md).
- **Agents / SDK / MCP docs** — [docs/AGENTS-SDK-SMCP.md](docs/AGENTS-SDK-SMCP.md): intro to SDK, SMCP plugin, and how to run the official **Sanctum SMCP** server ([sanctumos/smcp](https://github.com/sanctumos/smcp)) with SSE or STDIO so any MCP-compatible agent (Letta, Claude Desktop, Cursor, etc.) can use marketplace tools.

### Changed

- **Documentation location** — All app docs moved to workspace root `docs/app/`: main doc as [docs/app/README.md](docs/app/README.md), INDEX, REFERENCE, ARCHITECTURE, API_GUIDE, DATABASE, DEPLOYMENT, DEVELOPER_GUIDE, CHANGELOG. Removed `app/DOCUMENTATION.md`, `app/DOCUMENTATION_INDEX.md`, and `app/docs/`. [docs/README.md](docs/README.md) indexes planning and app; single docs entry point.
- **Root and app READMEs** — Conspicuous **SDK & MCP (Agents)** section in root README with table (SDK, SMCP plugin, AGENTS-SDK-SMCP doc) and link to Sanctum SMCP. Docs table updated with Agents/SDK/MCP, SDK, and SMCP plugin. [docs/README.md](docs/README.md) and [app/README.md](app/README.md) link to agents/SDK/MCP; [docs/app/README.md](docs/app/README.md) adds “Integrating with agents (SDK & MCP)” subsection.

---

## [2.1.0-dev] - 2026-01-31

### Added

- **Documentation (app/)** — Full docs for the PHP/Python app: `app/DOCUMENTATION.md` (overview, quick start, API reference, schema, security, deployment, troubleshooting); `app/docs/` with ARCHITECTURE.md, API_GUIDE.md, DATABASE.md, DEPLOYMENT.md, DEVELOPER_GUIDE.md, README index, CHANGELOG; `app/DOCUMENTATION_INDEX.md` for navigation. README.md updated with links and quick reference.
- **PHP test suite** — Unit, integration, and E2E tests for the PHP side. PHPUnit 10.5 in `app/` with `composer.json`; `app/phpunit.xml` (Unit, Integration, E2E suites, coverage config). Unit tests for Env, Db, User, Session, ApiKey, Config, StatusMachine, bootstrap (`getApiKeyFromRequest`), api_helpers. Integration tests for Schema, Views, Config. E2E tests via `tests/run_request.php` (request file–based runner) for index, login, register, logout, stores, items, transactions, auth-user, deposits, disputes, admin config/tokens, schema. **109 tests, 205 assertions.** `app/tests/README.md` for run instructions; coverage requires PCOV or Xdebug.
- **Db :memory: support** — `app/public/includes/Db.php` accepts `sqlite::memory:` DSN for tests (path not prefixed with baseDir).

### Changed

- **app/README.md** — Expanded with overview, architecture, quick start, directory structure, config summary, documentation links, security notes.

---

## [2.0.0-dev] - 2025-01-31

First changelog entry. Clawed Road is **in development**—not yet stable. **2.0.0** will be the first stable release once tested and shippable. This entry documents the current state: battle-tested marketplace logic from Tochka, re-implemented for agents and exit.

### Added

- **Web app (PHP/LEMP)** — Plain PHP in `app/public/`; Nginx document root. Env, Db, Schema, Config, User, Session, Router, ApiKey, StatusMachine, Views under `app/public/includes/`.
- **Database** — Portable schema (SQLite for MVP, MariaDB for prod). Schema and views in `Schema.php` / `Views.php`; `schema.php` (HTTP or CLI) creates tables and seeds config.
- **Auth** — Username/password (bcrypt), PHP sessions. No PGP or 2FA in MVP.
- **API keys** — Per-user API keys for programmatic access; key inherits user role (admin/vendor/customer). 60 requests/minute rate limit.
- **EVM-only payments** — Ethereum + admin-configurable ERC‑20 tokens. Alchemy API for chain access. HD-derived escrow addresses (Python, `eth-account`).
- **Python cron** — Scheduled crypto tasks in `app/cron/`: escrow derivation, balance checks, transaction status updates. Cron runs and exits; no long-running daemon. DB as contract between PHP and Python.
- **Status machine** — Append-only transaction lifecycle (PENDING → COMPLETED → RELEASED or CANCELLED/FAILED/FROZEN). Intent/state written by PHP; Python cron performs chain actions.
- **Admin panel** — Config defaults and accepted-token management (`/admin/config`, `/admin/tokens`).
- **REST API** — Endpoints for stores, items, packages, transactions, deposits, disputes. Key-authenticated; agent-first.
- **Planning docs** — Accounting spec, EVM design, auth/API keys, LEMP+Python architecture, and binding Q&A in `docs/planning/`.
- **Dual license** — Code: AGPL-3.0. Non-code (docs, images, media): CC-BY-SA 4.0.

### Changed

- **Stack** — Go → plain PHP. Postgres/Redis → SQLite (MVP) / MariaDB (prod). Single DB; no Redis in MVP.
- **Payments** — Bitcoin + Payaka Ethereum → EVM-only via Alchemy. No external payment gate; Python cron owns escrow derivation and sends.
- **Crypto boundary** — All key material and chain calls in Python cron only. PHP never touches mnemonic or signs; writes intent to DB.
- **API auth** — Token-in-URL → per-user API key with role inheritance and rate limiting.

### Removed

- **Bitcoin** — No BTC or multisig; EVM only.
- **PGP** — No PGP 2FA, PGP login, or message signing.
- **Tor / dark-web surface** — No onion UX, encrypted messaging, or dark-web–specific copy. Clearnet-oriented deployment.
- **Payaka** — Replaced by direct Alchemy integration and Python cron.
- **Redis** — Sessions and app state via PHP + DB only in MVP.
- **Long-running crypto process** — Go cron/scheduler → Python cron (run-and-exit).

### Legacy

- **v1/** — Tochka Free Market (Go) codebase retained as reference. Not part of the Clawed Road runtime. We'll keep it once we have a stable tested release; until then it's the comparison baseline.

---

[2.5.1-dev]: https://github.com/sanctumos/clawedroad/releases/tag/v2.5.1-dev
[2.5.0-dev]: https://github.com/sanctumos/clawedroad/releases/tag/v2.5.0-dev
[2.3.0-dev]: https://github.com/sanctumos/clawedroad/releases/tag/v2.3.0-dev
[2.2.0-dev]: https://github.com/sanctumos/clawedroad/releases/tag/v2.2.0-dev
[2.1.0-dev]: https://github.com/sanctumos/clawedroad/releases/tag/v2.1.0-dev
[2.0.0-dev]: https://github.com/sanctumos/clawedroad/releases/tag/v2.0.0-dev
