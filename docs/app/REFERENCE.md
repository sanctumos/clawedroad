# Documentation Index

Complete documentation for the Marketplace application.

## Quick Links

- **[Main Documentation](README.md)** - Comprehensive overview and quick start guide
- **[Architecture](ARCHITECTURE.md)** - System architecture and design patterns
- **[API Guide](API_GUIDE.md)** - Complete API reference with examples
- **[Database Schema](DATABASE.md)** - Database tables, views, and queries
- **[Deployment Guide](DEPLOYMENT.md)** - Production deployment instructions

## Documentation Structure

### Main Documentation (README.md)

The primary documentation file covering:
- System overview and features
- Architecture overview
- Quick start guide
- Directory structure
- Core components (PHP and Python)
- API reference
- Database schema overview
- Security features
- Deployment checklist
- Troubleshooting

**Start here** if you're new to the project.

### Architecture Documentation (ARCHITECTURE.md)

Deep dive into system architecture:
- High-level architecture diagram
- Component interactions
- Transaction creation flow
- Authentication flow
- Data flow patterns (append-only, intent-based)
- Security architecture
- Database architecture
- Scalability considerations
- Technology choices and rationale
- Future enhancements

**Read this** to understand how the system works internally.

### API Guide (API_GUIDE.md)

Complete API reference:
- Getting started with the API
- Authentication methods (session and API key)
- Rate limiting
- Error handling
- Endpoint reference (all endpoints with examples)
- Code examples (Python, JavaScript, cURL)
- Best practices

**Use this** for integrating with the API.

### Database Schema (DATABASE.md)

Complete database reference:
- Entity relationship diagram
- Table reference (all 23 tables)
- View reference (5 views)
- Common queries
- Indexes
- Data types
- Migration guide

**Refer to this** when working with the database.

### Deployment Guide (DEPLOYMENT.md)

Production deployment instructions:
- Prerequisites (hardware and software)
- Server setup
- Application installation
- Database configuration
- Web server configuration (Nginx)
- Cron setup
- Security hardening
- Monitoring setup
- Backup strategy
- Troubleshooting

**Follow this** to deploy to production.

## Quick Reference

### Key Concepts

**LEMP Design**: URL path = file path. One PHP script per endpoint. No front controller.

**Append-Only Status Machine**: Transaction statuses are never updated, only appended. Complete audit trail.

**Intent-Based Blockchain Operations**: PHP writes intents, Python cron executes them. PHP never has access to private keys.

**Secret Separation**: PHP loads only web-related environment variables. Python loads blockchain secrets (mnemonic, API keys).

**HD Wallet Derivation**: Each transaction gets a unique escrow address derived from mnemonic + transaction UUID.

### Common Tasks

#### Create a New User
```bash
curl -X POST http://localhost/register.php \
  -d "username=alice&password=secret123"
```

#### Create an API Key
```bash
curl -X POST http://localhost/api/keys.php \
  -b cookies.txt \
  -d "name=My API Key"
```

#### Create a Transaction
```bash
curl -X POST http://localhost/api/transactions.php \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d "package_uuid=pkg123&required_amount=0.1&chain_id=1&currency=ETH"
```

#### Check Transaction Status
```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://localhost/api/transactions.php | jq '.transactions[] | select(.uuid=="tx123")'
```

#### Run Database Migration
```bash
php public/schema.php
```

#### Run Cron Manually
```bash
# From app/ directory
python cron/cron.py

# From repo root
python app/cron/cron.py
```

### File Locations

| Component | Location |
|-----------|----------|
| Main documentation | `docs/app/README.md` |
| Environment config | `app/.env` |
| Database (SQLite) | `app/db/store.sqlite` |
| PHP classes | `app/public/includes/` |
| API endpoints | `app/public/api/` |
| Admin endpoints | `app/public/admin/` |
| Python cron | `app/cron/` |
| Nginx config | `app/nginx.conf.example` |
| Logs | `/var/log/` |

### Important URLs

| URL | Description | Auth Required |
|-----|-------------|---------------|
| `/` | Home page | No |
| `/register.php` | User registration | No |
| `/login.php` | User login | No |
| `/logout.php` | User logout | Session |
| `/api/stores.php` | List/create stores | No/Session |
| `/api/items.php` | List/create items | No/Session |
| `/api/transactions.php` | List/create transactions | API Key/Session |
| `/api/transaction-actions.php` | Request release/cancel/partial-refund intents | API Key/Session |
| `/api/keys.php` | Manage API keys | Session |
| `/admin/config.php` | System configuration | Admin |
| `/admin/tokens.php` | Accepted tokens | Admin |

### Database Tables

| Table | Purpose |
|-------|---------|
| `users` | User accounts |
| `stores` | Vendor stores |
| `items` | Products |
| `packages` | Product variants |
| `transactions` | Core transaction records |
| `evm_transactions` | EVM-specific data |
| `transaction_statuses` | Status log (append-only) |
| `transaction_intents` | Blockchain action intents |
| `shipping_statuses` | Shipping status log |
| `payment_receipts` | Blockchain receipts |
| `referral_payments` | Referral commissions |
| `deposits` | Vendor deposits |
| `disputes` | Dispute records |
| `config` | System configuration |
| `api_keys` | API keys |
| `accepted_tokens` | Payment tokens |

### Transaction Statuses

| Status | Description |
|--------|-------------|
| `PENDING` | Awaiting payment |
| `COMPLETED` | Payment received |
| `RELEASED` | Funds released to vendor |
| `FAILED` | Transaction failed |
| `CANCELLED` | Transaction cancelled (refunded) |
| `FROZEN` | Transaction frozen (dispute) |

### User Roles

| Role | Permissions |
|------|-------------|
| `admin` | Full system access |
| `staff` | Limited admin access |
| `vendor` | Store management |
| `customer` | Purchase items |

### Chain IDs

| Chain ID | Network |
|----------|---------|
| 1 | Ethereum Mainnet |
| 11155111 | Sepolia Testnet |
| 8453 | Base |
| 137 | Polygon |
| 42161 | Arbitrum One |

## Getting Help

### Documentation

1. Read the relevant documentation file above
2. Check the troubleshooting section in [README.md](README.md)
3. Review the deployment guide for production issues

### Common Issues

**Database connection errors**: Check `.env` file and database credentials

**Escrow address not generated**: Check cron is running and MNEMONIC is set

**Transaction stuck in PENDING**: Check Alchemy API key and balance polling

**502 Bad Gateway**: Check PHP-FPM is running and socket permissions

**Rate limit errors**: Slow down requests or increase limit

### Logs

Check these log files for errors:

- Nginx: `/var/log/nginx/marketplace-error.log`
- PHP-FPM: `/var/log/php-fpm/marketplace-error.log`
- Cron: `/var/log/marketplace-cron.log`
- MariaDB: `/var/log/mysql/error.log`

### Support

For issues not covered in documentation:
1. Check the troubleshooting sections
2. Review log files for error messages
3. Consult the relevant documentation file
4. Contact the development team

## Contributing to Documentation

### Documentation Standards

- Use Markdown format
- Include code examples
- Add table of contents for long documents
- Use consistent formatting
- Keep examples up to date
- Include version and date at bottom

### Updating Documentation

1. Edit the relevant `.md` file
2. Update the version and date
3. Test all code examples
4. Update this index if adding new files
5. Commit with descriptive message

### Documentation Files

All documentation files are in **`docs/app/`** (workspace root).

---

**Index Version**: 1.0.1  
**Last Updated**: February 7, 2026  
**Documentation Maintainer**: Development Team
