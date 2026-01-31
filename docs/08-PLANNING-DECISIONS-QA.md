# Planning Decisions (Q&A)

This document records decisions made during the planning phase. Treat these as binding for MVP and roadmap. **Do not start implementation without these being reflected in the rest of the docs.**

---

## 1. Accounting and Business Rules

### 1.1 Completion threshold and partial-refund splits

- **Decision**: Keep all current hardcoded values (e.g. 5% completion tolerance; partial-refund vendor/buyer/resolver splits including the 0.05 and 0.1) but make them **defaults** that are **configurable in an admin panel**.
- **Implication**: **Site-level admin panel** (same as existing site admin role) must expose these settings in **MVP**. Stored in config/settings table; code reads config, falls back to current hardcoded defaults.

### 1.2 Vendor referral

- **Decision**: **Not in MVP.** Put vendor referral (vendor inviter commission) on the **roadmap**.
- **Implication**: MVP implements buyer referral only (if at all). Vendor inviter payout logic is deferred.

### 1.3 Multisig / escrow model

- **Decision**: **Single-key escrow** for MVP. Gnosis Safe or other multisig/co-signed escrow is on the **roadmap** (“a hundred ways to skin that cat”; get to MVP quickly).
- **Implication**: One escrow address per transaction, key derived and held by Python service. No 2-of-2 or Safe in MVP.

---

## 2. EVM, Tokens, Chain, Keys, Alchemy

### 2.1 Tokens

- **Decision**: Accepted tokens (ETH + which ERC‑20s) are **configurable in the admin panel**. Token must be **Alchemy-supported**. If the API returns “not found” or “no price data” for a configured token, **fail** (do not allow that token for payments).
- **Implication**: Admin can add/remove token contracts (or symbols). At runtime, balance/price checks via Alchemy; missing or unsupported token → hard fail, no silent fallback.

### 2.2 Chains

- **Decision**: **Mainnet**, **Sepolia** (staging), and **Base** are the only supported chains for now.
- **Implication**: Chain ID / network is a config choice (mainnet vs Sepolia vs Base). No other L2s or sidechains in MVP.

### 2.3 Escrow key management

- **Decision**: **HD-derived escrow keys from a single mnemonic.** One mnemonic → infinite deterministic escrow addresses. Sweet spot; no downside for MVP.
- **Implication**: Python holds one mnemonic; derives per-transaction (or per-order) address via BIP-32/44 path. Mnemonic in .env or secure secret store; never in DB or PHP.

### 2.4 Alchemy

- **Decision**: Yes, Alchemy account exists for testing; will likely create a **new Alchemy account for prod**. **Roadmap**: move to a more decentralized architecture later; for MVP, Alchemy is fine.
- **Implication**: Design for Alchemy API (or Alchemy-compatible RPC) as the EVM provider. No multi-provider or decentralized RPC in MVP.

---

## 3. Architecture: PHP, Python, DB

### 3.1 PHP

- **Decision**: **Plain PHP.** No framework (no Laravel, Symfony, etc.). K.I.S.S.
- **Implication**: Vanilla PHP for web and API; structure and routing as needed without framework conventions.

### 3.2 Python execution model

- **Decision**: **Cron**, not a long-running process. Python runs on a schedule (e.g. every 1–5 min), does its work, exits.
- **Implication**: No “async loop” daemon. Cron job: update pending tx, fail old pending, release old completed, reconcile, wallet balances, etc. Internal-only, so cron is acceptable.

### 3.3 Database

- **Decision**: **Write for both** SQLite and MariaDB. **Dev**: SQLite. **Prod**: MariaDB. **.env configurable** which driver we use (e.g. `DB_DRIVER=sqlite` vs `mariadb`). Postgres is overkill.
- **Implication**: Schema and queries must be portable (SQLite and MariaDB). Avoid Postgres-specific features. Use .env to select DB driver; dev server uses SQLite, prod sets up MariaDB and uses that.

---

## 4. Auth, API Keys, Sessions

### 4.1 2FA

- **Decision**: **No 2FA in MVP.** Easy U/P and API key creation only. API is primarily for **agents** to use.
- **Implication**: No TOTP, no PGP, no second factor. Add 2FA on roadmap if needed later.

### 4.2 API key scoping

- **Decision**: **Account-level scoping.** API key **inherits user level**: admin, vendor, or customer. Obviously we need this.
- **Implication**: No fine-grained scopes (e.g. `read` vs `write`) in MVP; key has same permissions as the user who created it. Enforce user role (admin/vendor/customer) on each request.

### 4.3 Sessions

- **Decision**: **PHP-owned sessions only.** Not Redis. K.I.S.S.
- **Implication**: Use PHP’s default session handler (file-based or DB-backed by PHP), not Redis. Single tenant, so no distributed session requirement.

---

## 5. Scope: Data, Mobile App

### 5.1 Old data and mobile app

- **Decision**: **We don’t care about old data.** Writing this like a **new app**; the old code is **reference architecture** only. **We don’t care about the mobile app.**
- **Implication**: No migration of existing Tochka DB. No compatibility with existing Tochka mobile app. API can be designed from scratch for agents and new clients.

---

## 6. Vendorship and Messages

### 6.1 Vendorship agreement

- **Decision**: **“I agree” is fine.** Doesn’t need to be a PDF. Just a **DB field** (e.g. agreed_at timestamp, user_id, agreement_version).
- **Implication**: Replace PGP-signed vendorship with: checkbox “I agree” + store agreement acceptance in DB (timestamp, user, optional version). No PDF signing.

### 6.2 Messages (E2E encryption)

- **Decision**: **No.** No E2E or encrypted messaging in MVP.
- **Implication**: Messageboard and transaction messages are plain text (over HTTPS). No “optional E2E later” in scope for MVP; can roadmap if needed.

---

## 7. Config and Deployment

### 7.1 Secrets

- **Decision**: **.env** for secrets (Alchemy API key, DB credentials, cookie/encryption salt, mnemonic, etc.).
- **Implication**: No vault or secret manager in MVP. .env (or env vars) only; .env not committed.

### 7.2 Tenancy and deployment

- **Decision**: **Single-tenant deployment.**
- **Implication**: One instance, one DB, one set of config. No multi-tenant or multi-instance design in MVP.

---

## 8. MVP Clarifications (Follow-up Q&A)

### 8.1 Admin panel

- **Decision**: **Site-level admin**; same as existing **site admin** role. Admin panel for configurable defaults (completion tolerance, partial-refund splits, accepted tokens) is **in MVP**.
- **Implication**: Only users with site admin role can access admin settings. No separate “superuser” concept beyond existing admin.

### 8.2 Listing price model

- **Decision**: **One base currency: USD**. Prices from **Alchemy Prices API** (REST: `tokens/by-symbol` for ETH/USD, `tokens/by-address` for ERC-20). Optional fallback: CoinGecko (or similar) if Alchemy pricing is unavailable.
- **Implication**: Vendors set listing prices in USD. At checkout/display, convert to ETH/token using Alchemy Prices API (no on-chain oracle). Schema: e.g. `package_prices` in USD; conversion at runtime via Prices API (Python or PHP can cache).

### 8.3 User wallets (buyer funding)

- **Decision**: **Buyer sends from external wallet only.** No in-app user wallets for funding in MVP.
- **Implication**: We show escrow address (and optional QR); buyer sends from their own wallet (MetaMask, etc.). No “fund from user wallet” (no keys/addresses we hold for buyers). Simplifies MVP.

### 8.4 Vendor deposits

- **Decision**: **Vendor deposits are in MVP** (insurance-style; withdraw to store admin).
- **Implication**: Implement deposit creation, balance check, withdraw flow per reference. EVM only (ETH + configured tokens); Python cron or on-demand for balance/withdraw.

### 8.5 Dispute resolver payout

- **Decision**: Resolver is **always staff**. Staff user must have an EVM address (for 10% partial-refund payout).
- **Implication**: When resolving a dispute with partial refund, payout goes to the resolver’s EVM address. Resolver = staff user; need resolver address on staff user profile or in config.

### 8.6 Commission wallet

- **Decision**: **One commission wallet address per chain** (mainnet, Sepolia, Base). Set in **.env** (not admin).
- **Implication**: e.g. `COMMISSION_WALLET_MAINNET`, `COMMISSION_WALLET_SEPOLIA`, `COMMISSION_WALLET_BASE` in .env. Python uses the one for the transaction’s chain.

### 8.7 Database driver selection

- **Decision**: **Write for both** SQLite and MariaDB; **.env configurable** which we use. Dev server = SQLite; prod = MariaDB.
- **Implication**: Same as 3.3: portable DDL and queries; .env selects driver at runtime.

### 8.8 Agent API (webhooks)

- **Decision**: **REST only** for now. Don’t worry about webhooks/callbacks for transaction status changes in MVP.
- **Implication**: Agents poll the API. No webhook subscription or callback URLs. Can roadmap later if needed.

### 8.9 External repos follow-up (price, API keys, rate limit, .env, treasury vs Tochka)

- **ETH/USD price**: **Alchemy Prices API** (by-symbol for ETH, by-address for ERC-20); optional CoinGecko fallback. (Treasury repo uses Coinbase for that project only.)
- **API key storage**: **Plain** in MVP (simpler, last_used). **Roadmap**: hashed storage.
- **Rate limit**: **Per API key**; default **60 requests per minute**. **Roadmap**: pay for higher access.
- **.env in PHP**: Load **only relevant** .env vars in PHP. .env is shared between Python and PHP, but PHP must not load secrets it doesn't need (avoid creating security exposure).
- **Treasury repo vs Tochka**: Use **tmp/treasury only for API logic** (Alchemy RPC, Web3, wallet signing patterns). **Do not use treasury's accounting or balance logic.** Use **Tochka (v1) code** for accounting and escrow balance/detection logic.
- **Python execution**: **Cron only** (one-shot every N min). No long-running process. Treasury's daemon/loop is reference only; we stick to cron.

---

## 9. Roadmap (Out of MVP)

For reference, items explicitly **not in MVP** but on the roadmap:

- Vendor referral (vendor inviter commission).
- Gnosis Safe or other multisig/co-signed escrow.
- More decentralized architecture (e.g. multi-RPC, fallbacks).
- 2FA (e.g. TOTP) if needed later.
- Webhooks/callbacks for agent notifications (REST polling only in MVP).
- API key storage: hashed (MVP = plain).
- Rate limit: pay for higher access (MVP = 60/min per key).

---

## 10. Summary Table

| Topic | Decision |
|-------|----------|
| Completion / partial refund | Keep hardcoded values as **defaults**; **site-level admin** panel, **in MVP**. |
| Vendor referral | **Roadmap**, not MVP. |
| Multisig | **Single-key escrow** in MVP; Safe/etc. on **roadmap**. |
| Tokens | **Admin-configurable**; must be Alchemy-supported; fail if no price/not found. |
| Listing price | **One base: USD**; **Alchemy Prices API** (by-symbol, by-address); optional CoinGecko fallback. |
| Chains | **Mainnet, Sepolia, Base** only. |
| Escrow keys | **HD-derived** from one mnemonic. |
| Commission wallet | **One per chain** (mainnet, Sepolia, Base); set in **.env**. |
| Alchemy | Use for MVP; **new prod account**; decentralize on **roadmap**. |
| Buyer funding | **External wallet only**; no in-app user wallets for funding in MVP. |
| Vendor deposits | **In MVP** (insurance-style; withdraw to store admin). |
| Dispute resolver | **Always staff**; staff needs EVM address for 10% payout. |
| PHP | **Plain PHP**, no framework. |
| Python | **Cron**, not long-running process. |
| DB | **Write for both** SQLite and MariaDB; **.env configurable**; dev=SQLite, prod=MariaDB. |
| 2FA | **No** in MVP. |
| API key scope | **Account-level**; inherits user role (admin/vendor/customer). |
| API for agents | **REST only**; no webhooks/callbacks in MVP. |
| Sessions | **PHP sessions**, not Redis. |
| Old data / mobile app | **Ignore**; new app; reference only. |
| Vendorship | **“I agree”** + DB field, no PDF. |
| Messages | **No** E2E in MVP. |
| Secrets | **.env**. |
| Deployment | **Single-tenant**. |
| ETH/USD price | **Alchemy Prices API** (by-symbol, by-address); optional CoinGecko. Treasury repo uses Coinbase for that project only. |
| API key storage | **Plain** in MVP; **hashed** on roadmap. |
| Rate limit | **Per API key**, 60/min default; **roadmap**: pay for higher. |
| .env in PHP | Load **only relevant** vars; shared with Python but no unnecessary secrets in PHP. |
| Treasury repo | **API logic only**; accounting/balance from **Tochka (v1)**. |
| Python execution | **Cron only**; no long-running daemon. |
