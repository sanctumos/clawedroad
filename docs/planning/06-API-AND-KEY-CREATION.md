# API and Key Creation

**Binding decisions:** See **08-PLANNING-DECISIONS-QA.md** (API primarily for **agents**; **account-level** scoping—API key **inherits user level** (admin/vendor/customer); no 2FA in MVP).

## 1. API on PHP

- **Scope**: The **marketplace API** (list items, stores, categories, countries; get item/store; book package; list/show transaction; release/cancel/shipped; wallet balance/actions; settings; support; disputes; etc.) should be re-implemented on the **PHP** side (plain PHP, no framework).
- **Auth**: By **API key**. Validate key (lookup by api_key in MVP; roadmap: hashed storage). Load user; **API key inherits user level**: admin, vendor, or customer. No fine-grained scopes in MVP; enforce role on each request.
- **No PGP**: No PGP 2FA or PGP login; U/P only for web; API key for API. **No 2FA in MVP.**

## 2. Current API Surface (Reference)

From `modules/marketplace/router.go` (apiRouter):

- **Auth**: GET/POST `/api/auth/login`, `/api/auth/register`; GET `/api/auth/user` (by token).
- **Discovery**: GET `/api/serp`, `/api/deals`, `/api/categories`, `/api/countries`, `/api/stores`.
- **User**: GET `/api/user/:username` (about user).
- **Store**: GET `/api/store/:store`, GET `/api/store/:store/item/:item`, POST `/api/store/:store/item/:item/package/:hash/book`.
- **Transactions**: GET `/api/transactions`, GET/POST `/api/transactions/:transaction`, POST release/cancel/shipped/rate.
- **Wallets**: GET `/api/wallet`, POST `/api/wallet/bitcoin/send`, `/api/wallet/ethereum/send`, GET bitcoin/ethereum actions.
- **Settings**: POST `/api/settings`, `/api/settings/store`; POST `/api/settings/pgp/step1`, `step2` (remove in new stack).
- **Item CMS**: GET `/api/item/:item/packages`, POST edit/delete item/package.
- **Support**: GET/POST `/api/support`, GET/POST `/api/support/:id`.
- **Messages**: GET `/api/messages`, GET/POST `/api/messages/:username`.
- **Disputes**: GET `/api/dispute`, POST `/api/dispute/start/:transaction_uuid`, GET/POST dispute by uuid, claim, partial_refund.
- **Verification**: GET/POST agreement, GET/POST verification plan (replace PGP signing with simple accept).
- **Staff**: Various GET/POST under `/api/staff/...` (users, stores, tickets, disputes, warnings, ban, grant staff/store).

Re-implement these endpoints in **PHP** (plain PHP); drop PGP; add **API key** auth; **account-level** scoping (key inherits user role: admin/vendor/customer). No old mobile app compatibility required (08).

## 3. User API Key Creation

### 3.1 Flow

- User (logged in via U/P) goes to **Settings → API** (or similar).
- Clicks “Create API key”.
- Optionally enters a **name** (e.g. “Mobile app”, “Script”).
- **No scope selection in MVP**: key automatically has same permissions as the user (admin / vendor / customer).
- Server generates a **secret** (e.g. 32 bytes, base64 or hex); shows it **once** (e.g. “Key sk_xxxx…yyyy”).
- Server stores **plain** API key in `api_keys` (MVP; **roadmap**: hashed storage per 08.9). Store key_prefix (e.g. first 8 chars) for display; last_used for audit.
- User (or agent) copies key and uses it in `Authorization: Bearer <secret>`, `X-API-KEY` header, or legacy `?api_key=<secret>`.

### 3.2 Key Storage (SQLite MVP / MariaDB prod)

- **api_keys**: id, user_uuid, name, api_key (plain in MVP; roadmap: hashed per 08.9), key_prefix, created_at, last_used_at, expires_at (optional). **No scopes column in MVP**; permissions = user role.
- **Validation**: On each API request, take Bearer token, `X-API-KEY` header, or `api_key` query param; lookup by api_key; update last_used_at; load user; check expiry; **enforce user role** (admin/vendor/customer) for the endpoint.
- **Rate limit**: **Per API key**; default **60 requests per minute** (08.9). Roadmap: pay for higher access. Enforce in PHP (e.g. DB-backed or in-memory window).

### 3.3 Revocation and Rotation

- User can **revoke** a key (delete or mark inactive); no need to store full key.
- **Rotation**: User creates new key, revokes old; clients switch to new key. Optional “expires_at” for auto-expiry.

### 3.4 Relation to Current APISession

- **Current**: One token per “session”; 10-day expiry; 2FA possible. Token in query `?token=...`.
- **Target**: Prefer **long-lived API keys** (optional expiry) for programmatic access; optional **short-lived session tokens** after U/P login for “API as logged-in user” (same surface as current token). Both can coexist: session token = 10d; API key = until revoked or expired.

## 4. Documentation

- Provide **API docs** (e.g. OpenAPI/Swagger) on the PHP site: endpoints, auth (Bearer / API key), request/response examples. No PGP or Tor references.
- Document **key creation** and **revocation** in user-facing help (Settings → API).

## 5. Checklist for Implementation

- [ ] Implement all marketplace API endpoints in PHP (from router list above, minus PGP).
- [ ] Add `api_keys` table and key creation/revocation endpoints (or pages).
- [ ] Validate API key (lookup plain key in MVP; roadmap: hashed) on each API request; enforce **rate limit** (per key, 60/min default); load user; enforce **user role** (admin/vendor/customer).
- [ ] Document API and key lifecycle for users and developers.
