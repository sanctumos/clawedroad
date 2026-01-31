# Migration Plan Documentation

This folder contains the analysis and migration plan for converting the Tochka Free Market codebase into an EVM-only, clearnet marketplace: **plain PHP** (no framework), **SQLite (MVP) / MariaDB (prod)**, **Python cron** for crypto, **Alchemy** for EVM, **API primarily for agents**. All binding planning decisions are in **08-PLANNING-DECISIONS-QA.md**.

## Document Index

| Doc | Purpose |
|-----|---------|
| [00-EXECUTIVE-SUMMARY.md](00-EXECUTIVE-SUMMARY.md) | High-level criteria, target state, document map, gating note. |
| [01-ACCOUNTING-SPECIFICATION.md](01-ACCOUNTING-SPECIFICATION.md) | **Gating.** Exhaustive accounting: status machine, amounts, commission, referral, release/cancel/partial refund, deposits, wallets, cron, DB views, invariants. Configurable defaults per 08. |
| [02-DARK-WEB-STRIP-OUT.md](02-DARK-WEB-STRIP-OUT.md) | What to remove: PGP, Tor/onion references, dark-web copy; vendorship = "I agree" + DB; no E2E messages. Per 08. |
| [03-EVM-AND-PAYMENTS.md](03-EVM-AND-PAYMENTS.md) | EVM-only; Alchemy; ETH + admin-configurable tokens (Alchemy-supported, fail if no price); chains mainnet/Sepolia/Base; HD-derived escrow. Per 08. |
| [04-AUTH-AND-API-KEYS.md](04-AUTH-AND-API-KEYS.md) | U/P-only; no 2FA in MVP; API key inherits user level; PHP sessions (not Redis). Per 08. |
| [05-ARCHITECTURE-LEMP-PYTHON.md](05-ARCHITECTURE-LEMP-PYTHON.md) | Plain PHP; Python **cron** (not long-running); SQLite MVP / MariaDB prod; .env secrets; single-tenant. Per 08. |
| [06-API-AND-KEY-CREATION.md](06-API-AND-KEY-CREATION.md) | PHP API scope; user API key creation; **account-level** scoping (inherits role). Per 08. |
| [07-CODEBASE-INVENTORY.md](07-CODEBASE-INVENTORY.md) | Entry points, module layout, where accounting/auth/EVM/dark-web live. |
| [08-PLANNING-DECISIONS-QA.md](08-PLANNING-DECISIONS-QA.md) | **Binding planning Q&A.** Configurable defaults, roadmap (vendor referral, multisig, 2FA, decentralize), PHP/Python/DB/auth/scope/secrets/tenancy. **Read before implementation.** |
| [09-EXTERNAL-REPOS-ANALYSIS.md](09-EXTERNAL-REPOS-ANALYSIS.md) | Findings from **tmp/treasury** (API logic only; do not use its accounting/balance) and **tmp/technonomicon.net** (PHP auth, API keys, rate limit, CSRF). Decisions in **08.9**; Tochka (v1) for accounting. |

## Gating: Accounting First

Before re-implementing in the new stack:

1. Read and sign off on **01-ACCOUNTING-SPECIFICATION.md**.
2. Confirm all money flows and invariants are captured.
3. Re-implement accounting in PHP (persistence/API) and Python (crypto/cron) per that spec.
4. Then proceed with full migration and feature work.

## Suggested Order of Use

1. **00** — Orientation and criteria.
2. **08** — **Read planning decisions** (binding for MVP and roadmap).
3. **01** — Deep read; sign off; use as spec for re-implementation (configurable defaults per 08).
4. **02** — Plan strip-out (PGP, Tor; vendorship = "I agree"; no E2E messages).
5. **03** — Design EVM/Alchemy, tokens (admin-configurable, Alchemy-only), chains, HD escrow.
6. **04** + **06** — Design auth (no 2FA, PHP sessions) and API keys (account-level scoping).
7. **05** — Design plain PHP + Python cron + SQLite/MariaDB.
8. **07** — Cross-reference when mapping Go → PHP/Python.
9. **09** — Reference external repos (treasury, technonomicon); resolve new questions and fold answers into 08.
