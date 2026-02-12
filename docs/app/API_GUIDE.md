# API Guide

Complete guide to the Marketplace API with examples and best practices.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Authentication](#authentication)
3. [Rate Limiting](#rate-limiting)
4. [Error Handling](#error-handling)
5. [Endpoint Reference](#endpoint-reference)
6. [Code Examples](#code-examples)
7. [Best Practices](#best-practices)

---

## Getting Started

### Base URL

```
Production: https://your-domain.com
Development: http://localhost
```

### Content Type

All API endpoints return JSON:
```
Content-Type: application/json
```

### HTTP Methods

- **GET**: Retrieve resources
- **POST**: Create resources or submit actions
- **No PUT/PATCH/DELETE**: Use POST with action parameters instead

---

## Authentication

### Session-Based Authentication

Used for web UI. Login to get a session cookie.

**Login**:
```bash
curl -X POST http://localhost/login.php \
  -d "username=alice&password=secret123"
```

**Response**:
```
Logged in as alice
```

The session cookie is automatically included in subsequent requests.

**Logout**:
```bash
curl http://localhost/logout.php
```

### API Key Authentication

Used for programmatic access. Create an API key via web UI or session-authenticated request.

**Create API Key** (requires session):
```bash
curl -X POST http://localhost/api/keys.php \
  -H "Cookie: store_..." \
  -d "name=My Application"
```

**Response**:
```json
{
  "id": 1,
  "name": "My Application",
  "key_prefix": "abc12345",
  "api_key": "abc12345def67890ghijklmnopqrstuv1234567890abcdef1234567890abcdef",
  "created_at": "2026-01-31 12:00:00"
}
```

**Important**: Save the `api_key` immediately. It cannot be retrieved later.

### Using API Keys

Three methods to include your API key:

**1. Authorization Header** (recommended):
```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://localhost/api/transactions.php
```

**2. X-API-Key Header**:
```bash
curl -H "X-API-Key: YOUR_API_KEY" \
  http://localhost/api/transactions.php
```

**3. Query Parameter**:
```bash
curl "http://localhost/api/transactions.php?token=YOUR_API_KEY"
```

### Agent Identity Authentication

Used for agent-first access. Agents send an identity token in a header; the backend verifies the token and maps it to a local user.

**Header**:
```bash
curl -H "X-Agent-Identity: YOUR_IDENTITY_TOKEN" \
  http://localhost/api/auth-user.php
```

**Notes**:
- The identity token is issued by your configured identity provider.
- On first successful verify, Clawed Road creates a linked user; subsequent requests use the existing user.

---

## Security

### CSRF Protection

Session-authenticated API endpoints that modify data require CSRF tokens to protect against Cross-Site Request Forgery attacks.

**Affected Endpoints**:
- `POST /api/stores.php` — Create store
- `POST /api/items.php` — Create item
- `POST /api/transactions.php` — Create transaction
- `POST /api/transaction-actions.php` — Transaction actions (session-authenticated)
- `POST /api/keys.php` — Create API key
- `POST /api/keys-revoke.php` — Revoke API key

**Why**: If a user has an active session and visits a malicious website, that site could otherwise submit POST requests using the user's session cookie.

**Obtaining a CSRF Token**:

For browser-based applications using session auth, obtain the CSRF token from any web form (they include a hidden `csrf_token` input), or call a page that initializes the session and read the token from `$_SESSION['csrf_token']`.

**Submitting the CSRF Token**:
```bash
curl -X POST http://localhost/api/stores.php \
  -b cookies.txt \
  -d "storename=MyStore&csrf_token=YOUR_CSRF_TOKEN"
```

**Recommendation**: For programmatic/automated access, use **API key authentication** instead of session authentication. API keys are passed explicitly in headers and are not vulnerable to CSRF attacks.

### Authentication Security Model

| Auth Method | CSRF Required | Use Case |
|-------------|---------------|----------|
| Session (Cookie) | **Yes** | Browser-based web UI |
| API Key (Header) | No | Programmatic/automated access |
| Agent Identity (Header) | No | Agent-first integrations |

---

## Rate Limiting

### Limits

- **60 requests per minute** per API key
- **60 requests per minute** per agent identity token
- Sliding window (last 60 seconds)

### Rate Limit Response

When limit exceeded:
```http
HTTP/1.1 429 Too Many Requests
Content-Type: application/json

{
  "error": "Rate limit exceeded"
}
```

### Best Practices

1. **Cache responses** when possible
2. **Batch requests** instead of making many small requests
3. **Implement exponential backoff** on 429 responses
4. **Monitor your usage** to avoid hitting limits

**Example Backoff**:
```python
import time
import requests

def api_request_with_backoff(url, max_retries=3):
    for attempt in range(max_retries):
        response = requests.get(url)
        if response.status_code == 429:
            wait_time = 2 ** attempt  # 1s, 2s, 4s
            time.sleep(wait_time)
            continue
        return response
    raise Exception("Rate limit exceeded after retries")
```

---

## Error Handling

### HTTP Status Codes

| Code | Meaning | Description |
|------|---------|-------------|
| 200 | OK | Request succeeded |
| 400 | Bad Request | Invalid parameters |
| 401 | Unauthorized | Missing or invalid authentication |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource not found |
| 405 | Method Not Allowed | Wrong HTTP method |
| 409 | Conflict | Resource already exists |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error |

### Error Response Format

```json
{
  "error": "Error message here"
}
```

### Common Errors

**Invalid API Key**:
```json
{
  "error": "Invalid API key"
}
```

**Missing Parameters**:
```json
{
  "error": "name and store_uuid required"
}
```

**Not Authenticated**:
```json
{
  "error": "Login required"
}
```

**Admin Only**:
```json
{
  "error": "Admin only"
}
```

---

## Endpoint Reference

### Public Endpoints

#### GET /

Health check endpoint.

**Response**: `OK` (plain text)

**Example**:
```bash
curl http://localhost/
```

#### GET /api/stores.php

List all stores (public, no auth required).

**Query Parameters**: None

**Response**:
```json
{
  "stores": [
    {
      "uuid": "abc123def456",
      "storename": "TechStore",
      "description": "Electronics and gadgets",
      "vendorship_agreed_at": "2026-01-31 12:00:00",
      "created_at": "2026-01-31 12:00:00"
    }
  ]
}
```

**Example**:
```bash
curl http://localhost/api/stores.php
```

#### GET /api/items.php

List items, optionally filtered by store.

**Query Parameters**:
- `store_uuid` (optional): Filter by store UUID

**Response**:
```json
{
  "items": [
    {
      "uuid": "item123abc",
      "name": "Laptop",
      "description": "High-performance laptop",
      "store_uuid": "abc123def456",
      "category_id": 1,
      "created_at": "2026-01-31 12:00:00"
    }
  ]
}
```

**Examples**:
```bash
# All items
curl http://localhost/api/items.php

# Items from specific store
curl "http://localhost/api/items.php?store_uuid=abc123def456"
```

### User Management

#### POST /register.php

Register a new user account. **Requires CSRF token** from GET request.

**Parameters**:
- `username` (string, required, max 16 chars)
- `password` (string, required, min 8 chars)
- `csrf_token` (string, required) — Obtain from GET /register.php response form
- `invite` (string, optional) — Invite code for gated registration

**Response**: Redirects to `/marketplace.php` on success (HTTP 302)

**Example** (two-step flow):
```bash
# Step 1: GET the form and save cookies
curl -c cookies.txt http://localhost/register.php -o register.html

# Step 2: Extract CSRF token (example using grep)
CSRF=$(grep -oP 'name="csrf_token"\s+value="\K[^"]+' register.html)

# Step 3: POST registration with CSRF token
curl -b cookies.txt -X POST http://localhost/register.php \
  -d "username=alice&password=secret123&csrf_token=$CSRF"

# With invite code:
curl -b cookies.txt -X POST http://localhost/register.php \
  -d "username=alice&password=secret123&csrf_token=$CSRF&invite=INVITE_CODE"
```

#### POST /login.php

Login to get a session cookie.

**Parameters**:
- `username` (string, required)
- `password` (string, required)

**Response**: `Logged in as {username}` (plain text)

**Example**:
```bash
curl -X POST http://localhost/login.php \
  -d "username=alice&password=secret123" \
  -c cookies.txt
```

#### GET /logout.php

Logout and destroy session.

**Response**: `Logged out` (plain text)

**Example**:
```bash
curl http://localhost/logout.php \
  -b cookies.txt
```

### Store Management

#### POST /api/stores.php

Create a new store (requires session + CSRF token).

**Parameters**:
- `storename` (string, required, max 16 chars)
- `description` (string, optional)
- `vendorship_agree` (string, "1" to agree to terms)
- `csrf_token` (string, required, session CSRF token)

**Response**:
```json
{
  "ok": true,
  "uuid": "store123abc"
}
```

**Example**:
```bash
curl -X POST http://localhost/api/stores.php \
  -b cookies.txt \
  -d "storename=MyStore&description=My awesome store&vendorship_agree=1&csrf_token=YOUR_CSRF_TOKEN"
```

### Item Management

#### POST /api/items.php

Create a new item (requires session + CSRF token).

**Parameters**:
- `name` (string, required)
- `description` (string, optional)
- `store_uuid` (string, required)
- `csrf_token` (string, required, session CSRF token)

**Response**:
```json
{
  "ok": true,
  "uuid": "item123abc"
}
```

**Example**:
```bash
curl -X POST http://localhost/api/items.php \
  -b cookies.txt \
  -d "name=Laptop&description=High-end laptop&store_uuid=store123abc&csrf_token=YOUR_CSRF_TOKEN"
```

### Transaction Management

#### GET /api/transactions.php

List transactions for the authenticated user (requires API key or session). Returns transactions where the user is the **buyer** OR transactions for **stores the user belongs to**. Returns up to 100 transactions, ordered by most recent first.

**Query Parameters**: None

**Response**:
```json
{
  "transactions": [
    {
      "uuid": "tx123abc",
      "type": "evm",
      "description": "",
      "current_amount": 0.1,
      "current_status": "COMPLETED",
      "current_shipping_status": "DISPATCH PENDING",
      "number_of_messages": 0,
      "required_amount": 0.1,
      "escrow_address": "0x1234567890abcdef1234567890abcdef12345678",
      "chain_id": 1,
      "currency": "ETH",
      "buyer_username": "alice",
      "storename": "TechStore",
      "dispute_uuid": null,
      "package_uuid": "pkg123",
      "store_uuid": "store123",
      "buyer_uuid": "buyer123",
      "updated_at": "2026-01-31 12:05:00",
      "created_at": "2026-01-31 12:00:00"
    }
  ]
}
```

**Example**:
```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://localhost/api/transactions.php
```

#### POST /api/transactions.php

Create a new transaction (requires session + CSRF token).

**Parameters**:
- `package_uuid` (string, required)
- `refund_address` (string, optional, EVM address)
- `required_amount` (float, required, amount in crypto)
- `chain_id` (int, optional, default 1 for Ethereum mainnet)
- `currency` (string, optional, default "ETH")
- `csrf_token` (string, required, session CSRF token)

**Response**:
```json
{
  "ok": true,
  "uuid": "tx123abc",
  "escrow_address_pending": true
}
```

**Note**: The escrow address will be generated by the Python cron within a few minutes.

**Example**:
```bash
curl -X POST http://localhost/api/transactions.php \
  -b cookies.txt \
  -d "package_uuid=pkg123&required_amount=0.1&chain_id=1&currency=ETH&refund_address=0xYourAddress&csrf_token=YOUR_CSRF_TOKEN"
```

#### POST /api/transaction-actions.php

Request a transaction action intent: `release`, `cancel`, or `partial_refund`.

**Authentication**:
- API key / agent identity: allowed (no CSRF)
- Session cookie: allowed but requires `csrf_token`

**Parameters**:
- `transaction_uuid` (string, required)
- `action` (string, required): `release` | `cancel` | `partial_refund`
- `refund_percent` (number, required for `partial_refund`, 1..100)
- `csrf_token` (string, required for session-authenticated requests)

**Permissions / rules** (mirrors web flows):
- Transaction access: buyer OR vendor (`store_users`) OR staff/admin.
- `release`: vendor or staff/admin only; only when current payment status is `COMPLETED` and there is no open dispute.
- `cancel`: buyer or staff/admin only; only when current payment status is `PENDING` and there is no open dispute.
- `partial_refund`: staff/admin only; requires an open dispute on the transaction.

**Response**:
```json
{
  "ok": true,
  "action": "release",
  "transaction_uuid": "tx123abc",
  "intent": "RELEASE"
}
```

**Example (API key)**:
```bash
curl -X POST http://localhost/api/transaction-actions.php \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d "transaction_uuid=tx123abc&action=release"
```

**Example (session + CSRF)**:
```bash
curl -X POST http://localhost/api/transaction-actions.php \
  -b cookies.txt \
  -d "transaction_uuid=tx123abc&action=cancel&csrf_token=YOUR_CSRF_TOKEN"
```

**Chain IDs**:
- `1`: Ethereum Mainnet
- `11155111`: Sepolia Testnet
- `8453`: Base
- `137`: Polygon

### API Key Management

#### GET /api/keys.php

List your API keys (requires session).

**Response**:
```json
{
  "keys": [
    {
      "id": 1,
      "name": "My Application",
      "key_prefix": "abc12345",
      "created_at": "2026-01-31 12:00:00",
      "last_used_at": "2026-01-31 13:00:00"
    }
  ]
}
```

**Example**:
```bash
curl http://localhost/api/keys.php \
  -b cookies.txt
```

#### POST /api/keys.php

Create a new API key (requires session + CSRF token).

**Parameters**:
- `name` (string, optional, key label)
- `csrf_token` (string, required, session CSRF token)

**Response**:
```json
{
  "id": 1,
  "name": "My Application",
  "key_prefix": "abc12345",
  "api_key": "abc12345def67890...",
  "created_at": "2026-01-31 12:00:00"
}
```

**Example**:
```bash
curl -X POST http://localhost/api/keys.php \
  -b cookies.txt \
  -d "name=Production API&csrf_token=YOUR_CSRF_TOKEN"
```

#### POST /api/keys-revoke.php

Revoke an API key (requires session + CSRF token).

**Parameters**:
- `id` (int, required, key ID from GET /api/keys.php)
- `csrf_token` (string, required, session CSRF token)

**Response**:
```json
{
  "ok": true
}
```

**Example**:
```bash
curl -X POST http://localhost/api/keys-revoke.php \
  -b cookies.txt \
  -d "id=1&csrf_token=YOUR_CSRF_TOKEN"
```

#### GET /api/auth-user.php

Get current user info for an API key (requires API key).

**Response**:
```json
{
  "username": "alice",
  "role": "customer",
  "user_uuid": "user123abc"
}
```

**Example**:
```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://localhost/api/auth-user.php
```

### Deposit Management

#### GET /api/deposits.php

List deposits for your stores (requires session).

**Response**:
```json
{
  "deposits": [
    {
      "uuid": "dep123",
      "store_uuid": "store123",
      "currency": "USD",
      "crypto": "ETH",
      "address": "0x...",
      "crypto_value": 0.5,
      "fiat_value": 1000.0,
      "currency_rate": 2000.0,
      "created_at": "2026-01-31 12:00:00"
    }
  ]
}
```

**Example**:
```bash
curl http://localhost/api/deposits.php \
  -b cookies.txt
```

### Dispute Management

#### GET /api/disputes.php

List disputes (requires session).

**Response**:
```json
{
  "disputes": [
    {
      "uuid": "dispute123",
      "status": "open",
      "resolver_user_uuid": null,
      "created_at": "2026-01-31 12:00:00"
    }
  ]
}
```

**Example**:
```bash
curl http://localhost/api/disputes.php \
  -b cookies.txt
```

### Admin Endpoints

All admin endpoints require admin role.

#### GET /admin/config.php

Get system configuration (requires admin session). Returns only the fixed set of editable configuration keys listed below.

**Note**: Other config keys exist in the database (e.g., `gold_account_referral_percent`, `silver_account_referral_percent`, `bronze_account_referral_percent`, `free_account_referral_percent`, `android_developer_username`, `android_developer_commission`) but are not exposed by this endpoint.

**Response**:
```json
{
  "pending_duration": "24h",
  "completed_duration": "336h",
  "stuck_duration": "720h",
  "completion_tolerance": "0.05",
  "partial_refund_resolver_percent": "0.10",
  "gold_account_commission": "0.02",
  "silver_account_commission": "0.05",
  "bronze_account_commission": "0.10",
  "free_account_commission": "0.20"
}
```

**Example**:
```bash
curl http://localhost/admin/config.php \
  -b cookies.txt
```

#### POST /admin/config.php

Update system configuration (requires admin session).

**Parameters**: Any configuration keys from GET response

**Response**:
```json
{
  "ok": true
}
```

**Example**:
```bash
curl -X POST http://localhost/admin/config.php \
  -b cookies.txt \
  -d "pending_duration=48h&completion_tolerance=0.03"
```

#### GET /admin/tokens.php

List accepted payment tokens (requires admin session).

**Response**:
```json
{
  "tokens": [
    {
      "id": 1,
      "chain_id": 1,
      "symbol": "ETH",
      "contract_address": null,
      "created_at": "2026-01-31 12:00:00"
    },
    {
      "id": 2,
      "chain_id": 1,
      "symbol": "USDT",
      "contract_address": "0xdac17f958d2ee523a2206206994597c13d831ec7",
      "created_at": "2026-01-31 12:00:00"
    }
  ]
}
```

**Example**:
```bash
curl http://localhost/admin/tokens.php \
  -b cookies.txt
```

#### POST /admin/tokens.php

Add an accepted payment token (requires admin session).

**Parameters**:
- `chain_id` (int, required, EVM chain ID)
- `symbol` (string, required, token symbol)
- `contract_address` (string, optional, null for native tokens)

**Response**:
```json
{
  "ok": true,
  "id": 3
}
```

**Example**:
```bash
# Add native token (ETH)
curl -X POST http://localhost/admin/tokens.php \
  -b cookies.txt \
  -d "chain_id=1&symbol=ETH"

# Add ERC-20 token (USDT)
curl -X POST http://localhost/admin/tokens.php \
  -b cookies.txt \
  -d "chain_id=1&symbol=USDT&contract_address=0xdac17f958d2ee523a2206206994597c13d831ec7"
```

#### POST /admin/tokens-remove.php

Remove an accepted payment token (requires admin session).

**Parameters**:
- `id` (int, required, token ID from GET /admin/tokens.php)

**Response**:
```json
{
  "ok": true
}
```

**Example**:
```bash
curl -X POST http://localhost/admin/tokens-remove.php \
  -b cookies.txt \
  -d "id=2"
```

---

## Code Examples

### Python

```python
import requests

class MarketplaceClient:
    def __init__(self, base_url, api_key):
        self.base_url = base_url
        self.api_key = api_key
        self.session = requests.Session()
        self.session.headers.update({
            'Authorization': f'Bearer {api_key}'
        })
    
    def get_stores(self):
        """List all stores"""
        response = self.session.get(f'{self.base_url}/api/stores.php')
        response.raise_for_status()
        return response.json()
    
    def get_transactions(self):
        """List transactions"""
        response = self.session.get(f'{self.base_url}/api/transactions.php')
        response.raise_for_status()
        return response.json()
    
    def create_transaction(self, package_uuid, required_amount, 
                          chain_id=1, currency='ETH', refund_address=None):
        """Create a new transaction"""
        data = {
            'package_uuid': package_uuid,
            'required_amount': required_amount,
            'chain_id': chain_id,
            'currency': currency
        }
        if refund_address:
            data['refund_address'] = refund_address
        
        response = self.session.post(
            f'{self.base_url}/api/transactions.php',
            data=data
        )
        response.raise_for_status()
        return response.json()

# Usage
client = MarketplaceClient('http://localhost', 'your_api_key_here')

# List stores
stores = client.get_stores()
print(f"Found {len(stores['stores'])} stores")

# Create transaction
tx = client.create_transaction(
    package_uuid='pkg123',
    required_amount=0.1,
    chain_id=1,
    currency='ETH'
)
print(f"Created transaction: {tx['uuid']}")
print(f"Escrow address will be available soon")

# Check transaction status
transactions = client.get_transactions()
for tx in transactions['transactions']:
    print(f"TX {tx['uuid']}: {tx['current_status']}")
```

### JavaScript (Node.js)

```javascript
const axios = require('axios');

class MarketplaceClient {
  constructor(baseUrl, apiKey) {
    this.baseUrl = baseUrl;
    this.client = axios.create({
      baseURL: baseUrl,
      headers: {
        'Authorization': `Bearer ${apiKey}`
      }
    });
  }

  async getStores() {
    const response = await this.client.get('/api/stores.php');
    return response.data;
  }

  async getTransactions() {
    const response = await this.client.get('/api/transactions.php');
    return response.data;
  }

  async createTransaction(packageUuid, requiredAmount, options = {}) {
    const data = new URLSearchParams({
      package_uuid: packageUuid,
      required_amount: requiredAmount,
      chain_id: options.chainId || 1,
      currency: options.currency || 'ETH'
    });

    if (options.refundAddress) {
      data.append('refund_address', options.refundAddress);
    }

    const response = await this.client.post('/api/transactions.php', data);
    return response.data;
  }
}

// Usage
(async () => {
  const client = new MarketplaceClient('http://localhost', 'your_api_key_here');

  // List stores
  const stores = await client.getStores();
  console.log(`Found ${stores.stores.length} stores`);

  // Create transaction
  const tx = await client.createTransaction('pkg123', 0.1, {
    chainId: 1,
    currency: 'ETH'
  });
  console.log(`Created transaction: ${tx.uuid}`);

  // Check transaction status
  const transactions = await client.getTransactions();
  transactions.transactions.forEach(tx => {
    console.log(`TX ${tx.uuid}: ${tx.current_status}`);
  });
})();
```

### cURL

```bash
#!/bin/bash

# Configuration
BASE_URL="http://localhost"
API_KEY="your_api_key_here"

# List stores
curl -s "${BASE_URL}/api/stores.php" | jq .

# List transactions
curl -s -H "Authorization: Bearer ${API_KEY}" \
  "${BASE_URL}/api/transactions.php" | jq .

# Create transaction
curl -s -H "Authorization: Bearer ${API_KEY}" \
  -X POST "${BASE_URL}/api/transactions.php" \
  -d "package_uuid=pkg123" \
  -d "required_amount=0.1" \
  -d "chain_id=1" \
  -d "currency=ETH" | jq .

# Get current user
curl -s -H "Authorization: Bearer ${API_KEY}" \
  "${BASE_URL}/api/auth-user.php" | jq .
```

---

## Best Practices

### 1. Always Use HTTPS in Production

```python
# Good
client = MarketplaceClient('https://marketplace.example.com', api_key)

# Bad (development only)
client = MarketplaceClient('http://marketplace.example.com', api_key)
```

### 2. Store API Keys Securely

```python
# Good - use environment variables
import os
api_key = os.environ['MARKETPLACE_API_KEY']

# Bad - hardcoded
api_key = 'abc123def456...'
```

### 3. Handle Rate Limits Gracefully

```python
import time

def api_call_with_retry(func, max_retries=3):
    for attempt in range(max_retries):
        try:
            return func()
        except requests.HTTPError as e:
            if e.response.status_code == 429:
                wait_time = 2 ** attempt
                print(f"Rate limited, waiting {wait_time}s...")
                time.sleep(wait_time)
            else:
                raise
    raise Exception("Max retries exceeded")
```

### 4. Validate Responses

```python
def get_transactions_safe(client):
    response = client.get_transactions()
    
    # Check response structure
    if 'transactions' not in response:
        raise ValueError("Invalid response format")
    
    # Validate each transaction
    for tx in response['transactions']:
        required_fields = ['uuid', 'current_status', 'escrow_address']
        for field in required_fields:
            if field not in tx:
                print(f"Warning: Transaction {tx.get('uuid', 'unknown')} missing field: {field}")
    
    return response['transactions']
```

### 5. Use Timeouts

```python
# Set reasonable timeouts
client = requests.Session()
client.request = lambda *args, **kwargs: requests.Session.request(
    client, *args, **{**kwargs, 'timeout': 10}
)
```

### 6. Log API Calls

```python
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def api_call(method, url, **kwargs):
    logger.info(f"{method} {url}")
    response = requests.request(method, url, **kwargs)
    logger.info(f"Response: {response.status_code}")
    return response
```

### 7. Monitor Transaction Status

```python
import time

def wait_for_escrow_address(client, tx_uuid, timeout=300):
    """Poll transaction until escrow address is assigned"""
    start_time = time.time()
    
    while time.time() - start_time < timeout:
        transactions = client.get_transactions()
        tx = next((t for t in transactions['transactions'] if t['uuid'] == tx_uuid), None)
        
        if tx and tx['escrow_address']:
            return tx['escrow_address']
        
        time.sleep(10)  # Poll every 10 seconds
    
    raise TimeoutError(f"Escrow address not assigned after {timeout}s")

# Usage
tx = client.create_transaction('pkg123', 0.1)
print(f"Transaction created: {tx['uuid']}")

escrow_address = wait_for_escrow_address(client, tx['uuid'])
print(f"Escrow address: {escrow_address}")
print(f"Send 0.1 ETH to {escrow_address}")
```

### 8. Handle Errors Properly

```python
try:
    tx = client.create_transaction('pkg123', 0.1)
except requests.HTTPError as e:
    if e.response.status_code == 400:
        print(f"Bad request: {e.response.json()['error']}")
    elif e.response.status_code == 401:
        print("Authentication failed - check API key")
    elif e.response.status_code == 429:
        print("Rate limit exceeded - slow down")
    else:
        print(f"HTTP error: {e}")
except requests.RequestException as e:
    print(f"Network error: {e}")
```

---

**Document Version**: 1.1  
**Last Updated**: February 3, 2026
