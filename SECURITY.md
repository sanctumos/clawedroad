# Security policy

## Supported versions

Security fixes are applied to the default branch of [sanctumos/clawedroad](https://github.com/sanctumos/clawedroad). Use the latest commit on that branch for production deployments.

## Reporting a vulnerability

Please report security issues privately so we can address them before public disclosure.

**Preferred:** open a **GitHub Security Advisory** on this repository (Maintainers can use *Security* → *Advisories* → *Report a vulnerability*).

If that is not available, contact the repository maintainers through the organization’s usual secure channel.

Please include:

- A short description of the issue and its impact
- Steps to reproduce (or a proof of concept), if possible
- Affected components (PHP app, Python cron, SDK, etc.)

We aim to acknowledge reports within a few business days. Thank you for helping keep users safe.

## Scope notes

- This stack intentionally separates **PHP** (no mnemonic / chain signing keys) from **Python cron** (HD mnemonic and RPC keys via environment). Protect the host, `.env`, and cron execution context accordingly.
- Automated/heuristic scans may flag development defaults (e.g. local URLs). Treat findings with engineering judgment; prefer reports grounded in exploitability against a correctly configured deployment.
