# Marketplace PHP App (LEMP)

**Quick Reference** – Full documentation: **[docs/app/README.md](../docs/app/README.md)**

## Overview

Cryptocurrency-based marketplace application built on LEMP stack with Python cron for blockchain operations.

**Key Features**:
- Multi-store marketplace with vendor/customer roles
- EVM-based escrow payments (Ethereum and compatible chains)
- HD-derived escrow addresses (BIP-32/44) for each transaction
- Automated transaction lifecycle via Python cron
- API key authentication with rate limiting (60 req/min)
- Session-based web authentication
- Admin configuration for commission rates and timeouts

## Architecture

**LEMP design:** URL path = file path. One PHP script per endpoint. Nginx serves `.php` files directly; no front controller for API/admin. API URLs include `.php`: e.g. `/api/stores.php`, `/api/auth-user.php`, `/admin/config.php`. Root `/` is **index.php**; auth pages are **login.php**, **register.php**, **logout.php**.

**Components**:
- **PHP (web layer)**: Handles HTTP requests, authentication, database operations
- **Python (cron layer)**: Handles blockchain operations (escrow derivation, balance polling, fund releases)
- **Database**: SQLite (dev) or MariaDB/MySQL (prod)
- **Nginx**: Web server and reverse proxy

## Quick Start

### Prerequisites

- PHP 8.0+ with extensions: `pdo`, `pdo_sqlite` or `pdo_mysql`, `mbstring`, `json`
- Python 3.8+ with pip
- Nginx
- SQLite 3 or MariaDB/MySQL

### Installation

1. **Configure Environment**:
   ```bash
   cp .env.example .env
   # Edit .env with your settings
   ```

2. **Install Python Dependencies**:
   ```bash
   pip install -r cron/requirements.txt
   ```

3. **Configure Nginx**:
   - Point document root at **public/** (see **nginx.conf.example**)
   - No `try_files` to index.php for `/api/` or `/admin/`—those are real files

4. **Initialize Database**:
   ```bash
   # Via CLI
   php public/schema.php
   
   # Or via HTTP
   curl http://localhost/schema.php
   ```

5. **Set Up Cron Job**:
   ```bash
   # Add to crontab (runs every 2 minutes)
   */2 * * * * cd /path/to/app && python3 cron/cron.py >> /var/log/marketplace-cron.log 2>&1
   ```

## Directory Structure

- **public/**: Web document root
  - **api/**: Public/authenticated API endpoints
  - **admin/**: Admin-only endpoints
  - **includes/**: Shared PHP classes
- **cron/**: Python blockchain automation
- **db/**: Database files (SQLite)
- **.env**: Environment configuration (copy from .env.example)

## Configuration

- **Document root**: Point Nginx at **public/** (see **nginx.conf.example**)
- **DB (SQLite)**: **db/** at same level as **public/**; file `db/store.sqlite`. `.env` uses `DB_DSN=sqlite:db/store.sqlite` (path relative to baseDir = app/)
- **Schema**: **public/schema.php** — run via HTTP (GET/POST) or CLI: `php schema.php` from public/ (baseDir = app/). Creates tables, views, seeds config
- **.env**: In **app/** (same level as public/ and db/). Copy from `app/.env.example` to `app/.env`. PHP loads only DB_*, SITE_*, session/cookie/CSRF salts. Python loads blockchain secrets (MNEMONIC, ALCHEMY_*, COMMISSION_WALLET_*)

## Documentation

All documentation is in the workspace root **`docs/app/`**:

- **[Main docs](../docs/app/README.md)** – overview, quick start, API, schema, deployment
- **[Index](../docs/app/INDEX.md)** – full doc index
- **[Quick reference](../docs/app/REFERENCE.md)** – URLs, tables, statuses
- **[Architecture](../docs/app/ARCHITECTURE.md)** · **[API](../docs/app/API_GUIDE.md)** · **[Database](../docs/app/DATABASE.md)** · **[Deployment](../docs/app/DEPLOYMENT.md)** · **[Developer guide](../docs/app/DEVELOPER_GUIDE.md)**

## Security

**Critical**: PHP and Python load different subsets of `.env` to prevent secret exposure:
- **PHP loads**: DB_*, SITE_*, SESSION_SALT, COOKIE_ENCRYPTION_SALT, CSRF_SALT
- **Python loads**: All of the above plus MNEMONIC, ALCHEMY_*, COMMISSION_WALLET_*
- **PHP never has access to blockchain secrets** (mnemonic, API keys)

## Support

For issues and questions:
1. Check [docs/app/README.md](../docs/app/README.md) troubleshooting section
2. Review [docs/app/DEPLOYMENT.md](../docs/app/DEPLOYMENT.md) for production issues
3. Check log files for error messages
4. See [docs/app/](../docs/app/) for all documentation
