# Agents, SDK & MCP (SMCP)

This document is the **entry point for integrating Clawed Road with AI agents**: Python SDK, SMCP plugin, and how to run an MCP server so any MCP-compatible agent can call marketplace tools.

---

## Overview

| What | Purpose |
|------|--------|
| **Python SDK** (`sdk/`) | Call the Marketplace REST API from Python (scripts, services, or your own agent code). |
| **SMCP plugin** (`smcp_plugin/marketplace/`) | Expose marketplace operations as **MCP tools** (list stores, create transactions, manage API keys, etc.) so an MCP server can offer them to agents. |
| **SMCP server** | Official **Sanctum SMCP** (or any MCP server that runs plugin CLIs). You run the server; agents connect via **SSE** or **STDIO**. |

**Important:** The Marketplace API is **agent-first**. The SDK and SMCP plugin are the recommended ways to give your agent architecture access to stores, items, transactions, and keys. Any agent that can connect to MCP (Sanctum/Letta, Claude Desktop, Cursor, or other MCP clients) can connect to an SSE or STDIO SMCP instance that has the marketplace plugin installed.

---

## Python SDK

- **Location:** [sdk/](../sdk/) at repo root  
- **Docs:** [sdk/README.md](../sdk/README.md)

**Install (from repo root):**

```bash
pip install -e sdk
```

**Use:**

```python
from sdk import MarketplaceClient

client = MarketplaceClient(base_url="https://market.example.com", api_key="your-key")
stores = client.list_stores()
transactions = client.list_transactions()
```

Supports **API key** auth (for list/get and most reads) and **session** auth via `login()` for create-store, create-item, create-transaction, keys, deposits, disputes. See [sdk/README.md](../sdk/README.md) for full API and examples.

---

## SMCP Plugin (MCP tools for the Marketplace)

- **Location:** [smcp_plugin/marketplace/](../smcp_plugin/marketplace/) at repo root  
- **Docs:** [smcp_plugin/marketplace/README.md](../smcp_plugin/marketplace/README.md), [smcp_plugin/marketplace/INSTALL.md](../smcp_plugin/marketplace/INSTALL.md)

The plugin exposes these as **MCP tools** (e.g. `marketplace__list-stores`, `marketplace__create-transaction`):

- **No auth:** `health`, `list-stores`, `list-items`
- **API key:** `get-auth-user`, `list-transactions`
- **Session (username + password):** `create-store`, `create-item`, `create-transaction`, `list-keys`, `create-key`, `revoke-key`, `list-deposits`, `list-disputes`

The plugin is a **CLI** that the SMCP server runs as a subprocess. You copy the plugin into the SMCP server’s `plugins/` directory and install the marketplace SDK in the same environment. See [smcp_plugin/marketplace/INSTALL.md](../smcp_plugin/marketplace/INSTALL.md) for step-by-step installation.

---

## Official Sanctum SMCP server — how to run it

To give **any MCP-compatible agent** access to the marketplace tools, you run an **MCP server** that loads the marketplace plugin. The reference implementation is **Sanctum SMCP** (Model Context Protocol server with plugin support).

### Official repo

- **Repository:** **[sanctumos/smcp](https://github.com/sanctumos/smcp)**  
- **Description:** Plugin-based MCP server; supports **SSE** (e.g. Letta, web clients) and **STDIO** (e.g. Claude Desktop, Cursor). Compatible with any client that speaks MCP.

### Why SMCP?

- **MCP-compliant:** Uses the Model Context Protocol; not tied to a single vendor.
- **Transport options:**  
  - **SSE** — for remote or web-based agents (e.g. Sanctum/Letta).  
  - **STDIO** — for local agents (Claude Desktop, Cursor, custom CLI clients).
- **Plugin model:** Tools are provided by plugins (e.g. our marketplace plugin). SMCP discovers plugins in its `plugins/` directory and registers their commands as tools (e.g. `marketplace__list-stores`).

### Quick setup (standalone SMCP)

1. **Clone and run SMCP**
   ```bash
   git clone https://github.com/sanctumos/smcp.git
   cd smcp
   python -m venv venv
   # Windows: venv\Scripts\activate
   # Linux/macOS: source venv/bin/activate
   pip install -r requirements.txt
   ```

2. **Install Marketplace SDK** (so the marketplace plugin can run)
   ```bash
   pip install -e /path/to/clawed-road/sdk
   ```

3. **Copy the Marketplace plugin into SMCP**
   ```bash
   cp -r /path/to/clawed-road/smcp_plugin/marketplace smcp/plugins/
   ```

4. **Start the server**
   - **SSE** (default, for remote/web clients):
     ```bash
     python smcp.py
     ```
     Server: `http://localhost:8000` (SSE at `/sse`, messages at `/messages/`).
   - **STDIO** (for local MCP clients like Claude Desktop / Cursor):
     ```bash
     python smcp.py --stdio
     ```

5. **Point your agent at the server**
   - **SSE:** Use your client’s MCP/SSE configuration to connect to `http://localhost:8000/sse` (or your deployed URL).
   - **STDIO:** Configure the client to run `python /path/to/smcp/smcp.py --stdio` (or use `smcp_stdio.py` if the repo provides it).

### Where to read more

- **SMCP docs (in the repo):**  
  - [Getting started](https://github.com/sanctumos/smcp/blob/main/docs/getting-started.md)  
  - [Plugin development](https://github.com/sanctumos/smcp/blob/main/docs/plugin-development-guide.md)  
  - [Deployment](https://github.com/sanctumos/smcp/blob/main/docs/deployment-guide.md)  
- **MCP:** [Model Context Protocol](https://modelcontextprotocol.io/)  
- **This repo:** [smcp_plugin/marketplace/INSTALL.md](../smcp_plugin/marketplace/INSTALL.md) for plugin-only install steps.

---

## Who can connect?

- **Sanctum / Letta** — SSE; configure the agent to use your SMCP SSE endpoint.
- **Claude Desktop, Cursor, other STDIO MCP clients** — Run SMCP with `--stdio` and point the client at the `smcp.py` (or stdio wrapper) command.
- **Any other MCP client** — If it supports SSE or STDIO MCP transport, it can use the same SMCP instance and thus the same marketplace tools.

The marketplace plugin does not care which agent architecture is calling; it only requires that the MCP server invokes the plugin CLI with the correct tool name and arguments.

---

## Summary

| Goal | Action |
|------|--------|
| Call the API from Python | Use the **SDK** (`pip install -e sdk`); see [sdk/README.md](../sdk/README.md). |
| Expose marketplace as MCP tools | Use the **SMCP plugin** in [smcp_plugin/marketplace/](../smcp_plugin/marketplace/); see [INSTALL.md](../smcp_plugin/marketplace/INSTALL.md). |
| Run an MCP server for agents | Use **Sanctum SMCP**: [sanctumos/smcp](https://github.com/sanctumos/smcp); run with SSE or `--stdio` and add the marketplace plugin to `plugins/`. |

All docs live under [docs/](README.md). For app/API details, see [docs/app/README.md](app/README.md).
