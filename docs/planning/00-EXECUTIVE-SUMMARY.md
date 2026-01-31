# Migration Plan: Executive Summary

## Current State

This codebase is **Tochka Free Market**—an open-source darknet marketplace (Go, Postgres, Redis). It supports:

- **Payments**: Bitcoin (including multisig escrow) and Ethereum via an external **Payaka** payment gate
- **Auth**: Username/password + optional PGP-based 2FA and PGP login
- **API**: REST API with token-based auth (`?token=...`), used by a mobile app
- **Accounting**: Escrow flow (PENDING → COMPLETED → RELEASED or CANCELLED/FAILED/FROZEN), commission splits, referral payments, dispute partial refunds

## Target State (Your Criteria)

| # | Criterion | Target |
|---|----------|--------|
| 1 | **EVM** | Use EVM only (Ethereum + configurable ERC‑20 tokens). No Bitcoin. |
| 2 | **Strip dark-web** | Remove PGP, encrypted messaging, Tor/onion references, dark-web–specific UX and copy. |
| 3 | **Accounting** | Iron-clad, exhaustively documented so it can be re-implemented in a new stack (gating). |
| 4 | **Payments** | ETH + any token you specify; **Alchemy API** for EVM access. |
| 5 | **Auth** | Standard username/password only; no PGP or message signing. |
| 6 | **Architecture** | Website: **Plain PHP** on LEMP. DB: **SQLite (MVP) / MariaDB (prod)**. Crypto: **internal-only Python cron** (not long-running). |
| 7 | **API & keys** | **PHP** API for programmatic access (primarily for **agents**); **per-user API key**; key **inherits user role** (admin/vendor/customer). |

**Binding planning decisions** (MVP and roadmap): see **08-PLANNING-DECISIONS-QA.md**.

## Document Map

- **01-ACCOUNTING-SPECIFICATION.md** — Exhaustive accounting spec (gating). All flows, states, splits, invariants, and DB views. Configurable defaults per 08.
- **02-DARK-WEB-STRIP-OUT.md** — What to remove (PGP, Tor, dark-web copy/features) and where it lives in code.
- **03-EVM-AND-PAYMENTS.md** — EVM-only design; Alchemy; ETH + admin-configurable tokens; chains (mainnet, Sepolia, Base); HD-derived escrow. Per 08.
- **04-AUTH-AND-API-KEYS.md** — U/P-only auth; no 2FA in MVP; API key inherits user level; PHP sessions. Per 08.
- **05-ARCHITECTURE-LEMP-PYTHON.md** — Plain PHP; Python **cron**; SQLite (MVP) / MariaDB (prod); boundaries. Per 08.
- **06-API-AND-KEY-CREATION.md** — PHP API scope; user API key creation; **account-level** scoping (inherits role). Per 08.
- **07-CODEBASE-INVENTORY.md** — File/module inventory, entry points, and where accounting/crypto/auth live.
- **08-PLANNING-DECISIONS-QA.md** — **Binding planning Q&A**: configurable defaults, roadmap items, PHP/Python/DB/auth/scope/secrets. Read this before implementation.

## Gating: Accounting First

Before re-implementing in PHP/Python:

1. **Read and sign off** on **01-ACCOUNTING-SPECIFICATION.md**.
2. **Confirm** all money flows (escrow, commission, referral, dispute splits) are captured and that invariants (e.g. “no double-release”) are explicit.
3. **Re-implement** accounting in the new stack (PHP for persistence/API, Python for crypto moves) following that spec.
4. **Then** proceed with full LEMP + Python migration and feature work.

## High-Level Migration Order

1. **Document accounting** (done in 01) and get sign-off.
2. **Design** EVM/Alchemy integration and Python crypto loop (03, 05).
3. **Define** PHP API and API key model (04, 06).
4. **Strip** dark-web features (02) from spec/design (actual code strip when porting).
5. **Implement** PHP site (SQLite then MariaDB) + Python cron + API/key creation per docs and **08-PLANNING-DECISIONS-QA.md**.
