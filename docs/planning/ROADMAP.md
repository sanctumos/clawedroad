# Clawed Road — Roadmap

Single list of items that have been roadmapped across planning docs (v2, v2.5, MOLTBOOK-RESEARCH). Not in any particular order; implement when needed.

**Sources:** [v2/08-PLANNING-DECISIONS-QA.md](v2/08-PLANNING-DECISIONS-QA.md) §9, [v2.5/README.md](v2.5/README.md) (Out of scope / Roadmap), [v2/01-ACCOUNTING-SPECIFICATION.md](v2/01-ACCOUNTING-SPECIFICATION.md), [v2/03-EVM-AND-PAYMENTS.md](v2/03-EVM-AND-PAYMENTS.md), [v2/04-AUTH-AND-API-KEYS.md](v2/04-AUTH-AND-API-KEYS.md), [v2/06-API-AND-KEY-CREATION.md](v2/06-API-AND-KEY-CREATION.md), [MOLTBOOK-RESEARCH.md](MOLTBOOK-RESEARCH.md).

---

## Roadmap list

- **Vendor bond** — Vendors must put up a bond (stake/deposit) before a store can list. Not implemented yet; we have vendorship agreement and vendor deposits for earnings/withdrawals only. *(MOLTBOOK-RESEARCH)*

- **Rate limit account creation** — Limit new account (registration) creation by IP (e.g. no more than one per 10 minutes), overridable in `.env` (e.g. `ACCOUNT_CREATION_MIN_INTERVAL_MINUTES=10`). *(MOLTBOOK-RESEARCH)*

- **Agent-first login / Moltbook-like** — Agent identity: accept/verify token (we issue or verify via provider), map to user, publish auth instructions. Generate Clawed Road skill dynamically on first run (per-site base URL). Add Clawed Road hook points (agent identity verified, first request, transaction by agent, etc.) and optional outbound webhook. *(MOLTBOOK-RESEARCH)*

- **Vendor referral** — Vendor inviter commission; buyer referral only in MVP. *(v2/08, v2.5)*

- **Multisig / Gnosis Safe escrow** — Single-key escrow in MVP; multisig or co-signed escrow on roadmap. *(v2/08, v2/03, v2.5)*

- **More decentralized architecture** — Move beyond single Alchemy account; multi-RPC, fallbacks, etc. *(v2/08, v2/03)*

- **2FA** — TOTP or similar; not in MVP. *(v2/08, v2/04, v2.5)*

- **Webhooks / callbacks for agents** — REST polling only in MVP; optional webhook subscription or callbacks for agent notifications. *(v2/08)*

- **API key storage: hashed** — MVP stores plain API key; roadmap: store hash only, validate with hash_equals. *(v2/08, v2/04, v2/06)*

- **Rate limit: pay for higher access** — Per API key 60/min in MVP; roadmap option to pay for higher rate. *(v2/08, v2/06)*

- **Impersonate** — Admin/staff "login as user" for support/debug; out of v2.5. *(v2.5)*

- **Verification plan page** — Gold/silver/bronze tiers; out of v2.5. Only vendorship agreement in scope. *(v2.5)*

- **Config to shorten auto-release when buyer confirmed** — Out of v2.5. *(v2.5)*

- **In-app buyer wallets / "fund from wallet"** — MVP: buyer sends from external wallet only. Roadmap: in-app user wallets and fund-from-wallet; wallet balance views then relevant. *(v2/08, v2/01, v2/03)*

- **Wallet balance views** — `v_user_*_wallet_balances`; implement when/if "fund from wallet" is added. *(v2/01)*

- **Optional E2E encryption for messages** — Messages plain text in MVP; can roadmap if needed. *(v2/08)*

- **Optional audit logging for release/cancel intents** — Log intents (target_type=transaction, action_type=release_request/cancel_request) for audit trail. *(v2.5/12)*
