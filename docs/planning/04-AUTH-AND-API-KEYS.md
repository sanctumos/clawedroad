# Auth and API Keys

**Binding decisions:** See **08-PLANNING-DECISIONS-QA.md** (no 2FA in MVP; API key inherits user level; PHP sessions, not Redis; API primarily for agents).

## 1. Authentication: Username/Password Only

### 1.1 Current State

- **Web**: Session-based auth; cookie after login. Optional PGP login and PGP 2FA.
- **API**: `?token=...` (APISession token). If user has 2FA, API can require second factor (PGP-based in current code).
- **Password**: Stored as hash; check via `util.PasswordHashV1(username, passphrase)` and `User.CheckPassphrase(passphrase)`.

### 1.2 Target State

- **Web (PHP/LEMP)**: Standard **username + password** login only. **PHP-owned sessions** (file or DB), **not Redis**. K.I.S.S. No PGP, no encrypted message signing. **No 2FA in MVP** (roadmap if needed).
- **API**: Authenticate with **API key** (per user). **API key inherits user level** (admin, vendor, or customer)—account-level scoping. No PGP 2FA. API is **primarily for agents** to use; easy U/P and API key creation.

## 2. API Key Model (For PHP)

### 2.1 Purpose

- Allow **programmatic access** to the marketplace: list items, create order, get transaction status, release/cancel (if allowed), etc.
- **One or more keys per user**: User can create several keys (e.g. for different integrations or revocation).

### 2.2 Suggested Schema (SQLite MVP / MariaDB prod)

- **api_keys** (or equivalent):
  - `id` (PK)
  - `user_uuid` (FK to users)
  - `name` (optional label, e.g. “Agent 1”, “Script”)
  - `api_key` (plain secret in **MVP**; **roadmap**: store hash only, validate with hash_equals — per 08.9)
  - `key_prefix` (e.g. first 8 chars for “Key xxx…yyy” display)
  - `created_at`, `last_used_at`, `expires_at` (optional)
  - **No fine-grained scopes in MVP**: key **inherits user role** (admin / vendor / customer). Enforce role on each request; no separate `scopes` column needed for MVP.

### 2.3 Key Creation Flow

- User requests “Create API key” (from account/settings).
- Server generates a **random secret** (e.g. 32 bytes, base64 or hex), shows it **once** to the user.
- **MVP**: Store `api_key` (plain) and `key_prefix`; validate by direct lookup. **Roadmap**: store only hash + prefix; validate with hash_equals.
- Client uses the secret in header (e.g. `Authorization: Bearer <secret>`, `X-API-KEY`) or query (legacy `?api_key=`); server validates by lookup (MVP) or hash comparison (roadmap).

### 2.4 Relation to Current APISession

- **Current**: `APISession` has Token, UserUuid, ExpiryDate, 2FA fields. Token is UUID-like, used as `?token=...`.
- **Target**: Either:
  - **Replace** APISession with api_keys: one key per “session” or long-lived key; validate by api_key lookup (MVP) or key_hash (roadmap); optional expiry; or
  - **Keep** short-lived tokens (e.g. 10d) issued after U/P login and stored in a `sessions` or `api_sessions` table, and **add** api_keys for long-lived programmatic access.

Recommendation: **API keys** for programmatic access (create/list/revoke in settings). Optional short-lived **session tokens** for “login via API” (U/P → token) if needed.

## 3. What to Remove (Auth)

- PGP login (web and API).
- PGP 2FA (IsTwoFactorSession, SecondFactorSecretText, PGP decrypt step).
- Any “require PGP” for vendor or buyer actions.
- Stored PGP public keys for auth/signing (keep only if you want to use them for something non-auth, e.g. optional “encrypt shipping address” — out of scope here).

## 4. What to Keep / Reimplement (Auth)

- User table: username, passphrase_hash (use secure hash e.g. bcrypt/argon2 in PHP).
- **PHP-owned sessions** (file or DB), **not Redis** (08).
- Rate limiting and abuse protection (current middleware) in PHP.
- “Authorized URLs” (public routes): login, register, captcha, static assets, etc.

## 5. Files (Current Go) to Mirror or Drop

- **Keep logic**: `middleware_auth.go` — authorized URLs, load user by session or token; drop PGP/2FA branches.
- **API auth**: `FindAPISessionByToken` → replace with “find user by API key” (lookup by api_key in MVP; by key_hash on roadmap).
- **Auth views**: Login/register POST; remove PGP login and PGP setup routes (see 02-DARK-WEB-STRIP-OUT.md).

API key creation and revocation are described in **06-API-AND-KEY-CREATION.md**.
