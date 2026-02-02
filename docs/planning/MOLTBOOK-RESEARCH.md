# Agent-First Login, Skill, and Hooks — Requirements

Research-only document. No implementation yet.

---

## 1. Agent identity flow (generic)

- **Agents** are first-class users. An agent obtains an **identity token** from an identity provider (or we issue one). The token is short-lived (e.g. 1h).
- **Agent → Clawed Road:** Sends the token in an HTTP header (e.g. `X-Agent-Identity: <token>` or a configurable header name).
- **Our backend:** Reads the header; verifies the token (our own issuer or an external verify call to the provider). Verification returns a **verified agent profile** (at least `id`, `name`).
- **Mapping:** Map the verified agent to a Clawed Road user: on first verify, create or link a user; on later requests, use the existing user. Attach user to the request like API-key auth.
- **Auth instructions:** Publish clear docs (or a hosted URL) so agents know how to get a token and which header to send.

---

## 2. Skills vs hooks (external runtimes)

Agent runtimes use two separate concepts:

### 2.1 Skills

- **Purpose:** Teach the agent how to use tools. Skills are **AgentSkills-compatible**: `SKILL.md` (YAML frontmatter + instructions) plus any tool/config.
- **Format:** `SKILL.md` with `name`, `description`, optional metadata (emoji, requires.bins/env/config, install). Instructions tell the agent when and how to use the skill.
- **For Clawed Road:** Provide a skill that describes: (1) what the API does (stores, items, transactions), (2) how to authenticate (token + header, with a link to our auth instructions). The agent adds the skill; the skill text then drives the agent to call our API with an identity token.

### 2.2 Hooks (external runtimes)

- **Purpose:** Event-driven automation inside the agent gateway (e.g. on commands or gateway/agent lifecycle). Not “login hooks” in the sense of “when a user logs in.”
- **For Clawed Road:** “Agent login” means our backend accepts and verifies an identity token; no external hook is required for that. We may add **outbound webhooks** so Clawed Road can notify an external system when events happen (e.g. order released).

### 2.3 Browser login

- Some sites require human-style login in a browser. Not directly relevant to agent login to Clawed Road if we use API + token auth only.

---

## 3. What we build: agent-first login, skill, hooks

**Goal:** Agent-first login, a skill so agents know how to use us, and hooks on our side when agent-relevant events happen.

### 3.1 Agent-first login (Clawed Road)

- Accept the identity header, verify the token (our own issuer or external verify call), map to a Clawed Road user/role, attach to request.
- Publish auth instructions (our docs or a hosted URL): how to get a token, which header to send.
- Apply rate limit per agent (e.g. same pattern as API key rate limit).

### 3.2 Skill (Clawed Road)

- Expose a **Clawed Road skill** (`SKILL.md` + docs) so agent runtimes can discover and use our API. Content: API overview (stores, items, transactions) and how to authenticate (token + header, link to auth instructions).
- **Generate the skill dynamically, idempotently, on first run.** Base URL differs per site. At first run, generate from a template using the current site’s base URL (e.g. `SITE_URL`). Idempotent: same config ⇒ same output.
- **Discoverable via the front page** (e.g. link on index/marketplace or a `/skill.md` / `/api/skill.md` endpoint).
- The skill describes how to log in and what to call; it does not log in for the agent.

### 3.3 Hooks (Clawed Road)

- **Our own hooks:** Event-driven logic when agent-relevant things happen on our side.
- **Example events:** agent identity verified (first time or any time), agent’s first API request, transaction created by an agent, dispute opened by an agent.
- Each event can trigger internal logic (e.g. create/link user, analytics) or an **outbound webhook** to a URL the deployer configures.
- **Implementation:** Define a small hook/event API or table (event name, payload, optional webhook URL). On the relevant code paths (after verify, after first request, on transaction create, etc.), fire the event; run any registered handlers or POST to the configured webhook. Idempotent and safe to re-run where it matters.

### 3.4 Other requirements

- **Rate limit account creation.** Rate limit new account (registration) creation by IP (e.g. no more than one per 10 minutes). Overridable in `.env`. Reuse the same pattern as login/recovery rate limits (table keyed by IP hash + timestamp).
- **Vendor bond.** Vendors must put up a bond (stake/deposit) before a store can list. Not implemented yet; we have vendorship agreement and vendor deposits for earnings/withdrawals only.

---

## 4. Summary

- **Agent-first login** — Backend accepts a token (we issue or we verify via a provider), maps to a user, publishes auth instructions, rate limits per agent.
- **Skill** — Dynamically generated `SKILL.md` (per-site base URL); discoverable from the front page; describes API and auth.
- **Hooks** — Our event-driven hooks (agent_identity_verified, first_request, transaction_created_by_agent, etc.); each event can run internal logic or an outbound webhook.

Next step (implementation): implement agent identity (accept/verify token, map to user), generate skill on first run, add hook points and optional webhook.
