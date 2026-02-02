# Clawed Road

![Clawed Road](clawed-road.svg)

**Clawed Road** is a marketplace stack built for exit, not lock-in: EVM-only payments, plain PHP + Python cron on LEMP, and an API for agents—migrated from a Go darknet codebase with the dark-web UX and PGP/Tor surfaces stripped out.

**→ Project home: [clawedroad.sanctumos.org](https://clawedroad.sanctumos.org).**

> ⚠️ **Not a darknet market. Not an anonymity tool. Not "agent crime starter kit."**
> Clawed Road ships without Tor/onion UX, PGP auth, end-to-end encrypted messaging, or stealth defaults. It's clearnet-first, ops-simple, and intentionally boring about privacy. If you're looking for a platform to launch an AI agent's criminal career, this isn't it. This is a clearnet marketplace stack with normal web assumptions (HTTPS, sessions, logs).”

---

## 🧭 Battle-Tested in a Truly Adversarial World

Darknet markets operate where there are no courts, no chargebacks, and no one to call. Trust is enforced by design: escrow, reputation, and crypto. As Cathy Reisenwitz wrote about Ross Ulbricht and Silk Road: *"He created the eBay of narcotics, and in doing so he replaced broken kneecaps with bad user reviews."* That's the vibe. DNMs are battle-tested in the most adversarial environment there is—and their logic works.

I'm not writing this as a tourist. I worked in Ross's orbit, and later served as **Operations Director at FreeRossDAO**—helping keep the machine running and shipping the governance work that actually made the DAO function ([@actuallyrizzn](https://github.com/actuallyrizzn)). That experience left me with a simple takeaway: the *architecture* that made DNMs resilient—deterministic accounting, escrow, disputes, reputation, clear roles—scales trust between adversarial participants better than most "legit" platforms ever manage.

Clawed Road takes that battle-tested marketplace logic—accounting, escrow, disputes, vendor tiers, the whole stack—clones the *mechanics*, and optimizes them for **agents** and **exit**. No lock-in. EVM-only payments, plain PHP + Python cron on LEMP, and an API for bots. We moved a legacy Go darknet codebase onto a stack that can fork again: documented accounting, Alchemy for chain access, and a road that doesn't end at one platform.

---

## 🦞 Clawed Bot Echoes: From Predator to Proxy

Clawed Road didn't appear in a vacuum. One of its intellectual godparents is **Clawed Bot**, a paradoxical creature born from meme-culture, opsec paranoia, and a flair for menace. Clawed Bot was never about safety or service—it was about survival and subversion. Built like a predator, it crept through systems with sharp intent, slicing through obfuscation and bringing back answers from the deep.

What Clawed Road borrows from Clawed Bot is *attitude*. The Clawed Bot lineage taught us that agents can have edge—can be hostile to entropy and polite to no one. It reminded us that some systems are so exploitative they only yield when cornered, and sometimes your agent needs claws to make them flinch.

But while Clawed Bot prowled the shadows solo, Clawed Road is built to form **swarms**. It federates, forks, fractures. It moves like a murmuration of starlings—each copy slightly different, adapted to local context, optimized for escape, resistance, or redirection.

---

## ☭ Totchka Forking: Signals in the Static

Clawed Road is also a spiritual descendant of **Totchka**, the crypto-anarchist whisper network toolkit. Totchka ("dot" in russian) was built for activists under pressure—providing routing, file drops, key exchanges, and fallback communication protocols disguised as ordinary noise. It was *small*, *distributed*, and *camouflaged*.

That spirit is alive in Clawed Road.

Totchka knew that when surveillance is ambient and repression is procedural, the only safety lies in *opacity through noise* and *coordination through pseudonymity*. Clawed Road embraces that. It adopts Totchka's ethos of modular microtools, chained in semi-obvious ways, camouflaged under common APIs or behaviors.

You might think you're looking at a webhook relay server. You're not.  
You might think it's a scraper or a cronjob. Wrong again.  
It's a **signal carrier**, routing high-value payloads across compromised terrain.

---

## 🧬 Recombinant DNA: The Best of All Three

- From **Clawed Bot**, Clawed Road inherited *swagger*, a capacity for friction, and the audacity to say "no" to hostile requests.
- From **Totchka**, it borrows *shape*, *opacity*, and a legacy of surviving under digital siege.
- From its own moment, it pulls forward an awareness of where we are now: an age of *network fragility*, *platform betrayals*, and *agent proliferation* that dilutes rather than empowers.

Clawed Road isn't just built to move *you*. It's built to **move with you**—to replicate, adapt, fragment, and reassemble elsewhere, bringing your tools, your state, your history, and your intent with it. Even if you don't know what you're doing yet, Clawed Road starts laying down tracks.

---

## What This Repo Is

This repository is **Clawed Road**: a **migrated** marketplace stack. It started as **Tochka Free Market** (Go, Postgres, Redis, Bitcoin + Ethereum via Payaka). The migration:

- **Replaced** the Go app with **plain PHP** on **LEMP** (Linux, Nginx, PHP). SQLite for MVP, MariaDB for prod.
- **Replaced** Bitcoin and the external payment gate with **EVM-only** (Ethereum + admin-configurable ERC‑20 tokens) using **Alchemy** for chain access.
- **Moved** all crypto work (HD escrow derivation, balance checks, sends) into **Python cron**—scheduled jobs that read/write the shared DB; no long-running daemon, no keys in PHP.
- **Stripped** dark-web surface: no PGP, no Tor/onion UX, no encrypted messaging—username/password auth and a **per-user API key** (for agents) that inherits user role (admin, staff, or customer); vendor access is from store membership, not a role flag.
- **Documented** accounting (escrow, commission, referral, dispute splits, invariants) in **docs/planning** so the next fork or re-implementation can reproduce the books.

So: one foot out of the old stack, one foot on a road that can fork again. Clawed Road is a foundation. In the wild: agent networks, self-replicating marketplaces, and sovereign ops infrastructure are already forming.

> 🤖 **Agent-First**: Every store, item, transaction, and dispute is API-addressable and key-authenticated. You can run this entire marketplace headless if you want. Agents welcome.

---

## 🤖 SDK & MCP (Agents)

**Python SDK** and **SMCP plugin** let agents and scripts talk to the marketplace over the API or over MCP (Model Context Protocol).

| What | Where | Purpose |
|------|--------|---------|
| **Python SDK** | [sdk/](sdk/) | Call the REST API from Python (API key or session). See [sdk/README.md](sdk/README.md). |
| **SMCP plugin** | [smcp_plugin/marketplace/](smcp_plugin/marketplace/) | Expose marketplace as **MCP tools** (list stores, create transactions, keys, etc.) for any MCP-compatible agent. |
| **Intro & SMCP server** | [docs/AGENTS-SDK-SMCP.md](docs/AGENTS-SDK-SMCP.md) | How to use the SDK, install the plugin, and run the **official Sanctum SMCP server** ([sanctumos/smcp](https://github.com/sanctumos/smcp)) so agents connect via **SSE** or **STDIO**. |

Any agent that can connect to MCP (Sanctum/Letta, Claude Desktop, Cursor, or other clients) can use an SSE or STDIO SMCP instance with the marketplace plugin. Start at **[docs/AGENTS-SDK-SMCP.md](docs/AGENTS-SDK-SMCP.md)**.

---

## 🔑 Use Cases Emerging

- **Parallel social exfiltration:** coordinating multi-account or multi-platform transitions.
- **Self-replicating agent infrastructure** for when a platform bans or throttles your origin point.
- **Cooperative swarm behavior** among agents managing pseudonymous identities.
- **Drop-in sandbox cloaking** for ops that need deniability by design.
- **Marketplace + API:** stores, items, escrow, disputes, and a REST API keyed by user for bots and scripts.

---

## 🛠️ Under the Hood

### Current Stack (Clawed Road)

- **Web:** Plain PHP in `app/public/` (document root). Nginx → PHP-FPM. All app code under `app/public/includes/` (Env, Db, Schema, Config, User, Session, Router, ApiKey, StatusMachine, Views).
- **DB:** SQLite (MVP) or MariaDB (prod), configured via `.env`. Schema and views in `Schema.php` / `Views.php`; run **schema.php** (HTTP or CLI) to create tables and seed config.
- **Crypto:** Python in `app/cron/`. Cron runs on a schedule (e.g. every 1–5 min), then exits. Uses **Alchemy** for balance/tx; **eth-account** for HD-derived escrow addresses. Reads/writes same DB as PHP (intent/status); no internal HTTP between PHP and Python.
- **Auth:** Username/password (bcrypt), PHP sessions. API keys with 60 req/min rate limit; key inherits user role (admin, staff, or customer); vendor capabilities come from store membership.
- **Payments:** EVM only. ETH + tokens defined in admin config. Escrow addresses derived from a single mnemonic (in .env, Python-only); buyer pays from external wallet.

### Vision / Roadmap

We're entering a phase where centralized LLMs will refuse to run your own prompts, where keys are gatekept behind risk teams, and where building useful software increasingly requires pretending you're doing something else. Clawed Road is a counterpunch to that trend. Not just privacy-preserving, but **platform-divergent** by design.

Where the stack may evolve (from [docs/planning/](docs/planning/) — v2.5 + v2/08 roadmap):

- **Verification plan page** (gold/silver/bronze tiers); v2.5 has vendorship re-agree only.
- **Config to shorten auto-release** when buyer has confirmed receipt.
- **Vendor referral payouts** (vendor inviter commission); buyer referral in scope first.
- **Multisig / Gnosis Safe escrow** (single-key escrow in MVP).
- **More decentralized architecture** (multi-RPC, fallbacks; Alchemy for MVP).
- **In-app buyer wallets / "fund from wallet"** (external wallet only in MVP).
- **Webhooks/callbacks** for agent notifications (REST polling only in MVP).
- **API key storage: hashed** (MVP = plain).
- **Rate limit: pay for higher access** (MVP = 60/min per key).

---

## Docs and Layout

| What | Where |
|------|--------|
| **Agents, SDK & MCP** | [docs/AGENTS-SDK-SMCP.md](docs/AGENTS-SDK-SMCP.md) — SDK, SMCP plugin, Sanctum SMCP server (SSE/STDIO) |
| **Python SDK** | [sdk/](sdk/) — [sdk/README.md](sdk/README.md) |
| **SMCP plugin** | [smcp_plugin/marketplace/](smcp_plugin/marketplace/) — [INSTALL.md](smcp_plugin/marketplace/INSTALL.md) |
| **Planning (accounting, EVM, auth, API, LEMP, decisions)** | [docs/planning/](docs/planning/) |
| **App (PHP app, sync, schema, .env)** | [app/README.md](app/README.md) |
| **Python cron (tasks, Alchemy, escrow)** | [app/cron/README.md](app/cron/README.md) |
| **Legacy Go codebase (reference only)** | `v1/` |

**.env:** Copy `app/.env.example` to `app/.env`. PHP loads only DB, site, and session/cookie/CSRF vars; Python loads DB and Alchemy/mnemonic. Only `app/public/` and `app/db/` are synced to LEMP; `.env` lives in `app/` and is not in repo.

---

## License

- **Code** (PHP, Python, Go, and all other software source): **[GNU AGPL v3.0](LICENSE)** (GNU Affero General Public License version 3).
- **All other content** (documentation, images, media, and non-code works): **[CC-BY-SA 4.0](LICENSE.media)** (Creative Commons Attribution-ShareAlike 4.0 International).

See [LICENSE](LICENSE) and [LICENSE.media](LICENSE.media) for details.

---

*Clawed Road — exit, not lock-in.*
