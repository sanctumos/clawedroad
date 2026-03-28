"""Alchemy API: balance (eth_getBalance), Prices API (ETH/USD by-symbol), raw tx broadcast."""
import requests
from decimal import Decimal


def _rpc_url(network: str, api_key: str) -> str:
    base = "https://eth-mainnet.g.alchemy.com/v2"
    if network and network.lower() != "mainnet":
        base = f"https://eth-{network.lower()}.g.alchemy.com/v2"
    return f"{base}/{api_key}"

def get_balance_wei(address: str, api_key: str, network: str = "mainnet") -> int:
    url = _rpc_url(network, api_key)
    r = requests.post(url, json={"jsonrpc": "2.0", "method": "eth_getBalance", "params": [address, "latest"], "id": 1}, timeout=10)
    r.raise_for_status()
    data = r.json()
    if "error" in data:
        raise RuntimeError(data["error"])
    return int(data.get("result", "0x0"), 16)

def wei_to_eth(wei: int) -> float:
    return float(Decimal(wei) / Decimal(10**18))

def get_eth_usd_price(api_key: str) -> float:
    url = "https://prices.g.alchemy.com/v1/tokens/by-symbol?symbols=ETH"
    r = requests.get(url, headers={"Authorization": f"Bearer {api_key}"}, timeout=10)
    if r.status_code != 200:
        return 0.0
    data = r.json()
    try:
        prices = data.get("data", [{}])[0].get("prices", [])
        for p in prices:
            if p.get("currency") == "USD":
                return float(p.get("price", 0))
    except (IndexError, KeyError, TypeError):
        pass
    return 0.0


def _rpc_post(network: str, api_key: str, method: str, params: list) -> dict:
    url = _rpc_url(network, api_key)
    r = requests.post(
        url,
        json={"jsonrpc": "2.0", "method": method, "params": params, "id": 1},
        timeout=30,
    )
    r.raise_for_status()
    data = r.json()
    if "error" in data:
        raise RuntimeError(str(data["error"]))
    return data


def eth_get_transaction_count(address: str, api_key: str, network: str = "mainnet") -> int:
    data = _rpc_post(network, api_key, "eth_getTransactionCount", [address, "latest"])
    return int(data.get("result", "0x0"), 16)


def eth_gas_price(api_key: str, network: str = "mainnet") -> int:
    data = _rpc_post(network, api_key, "eth_gasPrice", [])
    return int(data.get("result", "0x0"), 16)


def eth_send_raw_transaction(signed_tx_hex: str, api_key: str, network: str = "mainnet") -> str:
    if not signed_tx_hex.startswith("0x"):
        signed_tx_hex = "0x" + signed_tx_hex
    data = _rpc_post(network, api_key, "eth_sendRawTransaction", [signed_tx_hex])
    return str(data.get("result", ""))


def eth_native_transfer_wei(
    from_account,
    to_address: str,
    chain_id: int,
    api_key: str,
    network: str,
    get_balance_wei_fn,
) -> str:
    """
    Send entire native balance minus legacy gas fee from from_account to to_address.
    Returns tx hash. Raises on RPC or signing errors.
    """
    from_address = from_account.address
    balance = get_balance_wei_fn(from_address)
    gas_price = eth_gas_price(api_key, network)
    gas_limit = 21_000
    fee = gas_price * gas_limit
    value = balance - fee
    if value <= 0:
        raise ValueError("insufficient balance for gas + transfer")
    nonce = eth_get_transaction_count(from_address, api_key, network)
    tx = {
        "nonce": nonce,
        "gasPrice": gas_price,
        "gas": gas_limit,
        "to": to_address,
        "value": value,
        "chainId": chain_id,
    }
    signed = from_account.sign_transaction(tx)
    raw = getattr(signed, "raw_transaction", None) or getattr(signed, "rawTransaction", None)
    hx = raw.hex() if hasattr(raw, "hex") else raw
    if not isinstance(hx, str):  # pragma: no cover — eth_account returns str/HexBytes; defensive
        hx = hx.hex()
    return eth_send_raw_transaction(hx, api_key, network)


def eth_native_send_value_wei(
    from_account,
    to_address: str,
    value_wei: int,
    chain_id: int,
    api_key: str,
    network: str,
    get_balance_wei_fn,
) -> str:
    """Send exactly value_wei to to_address (plus gas paid from same account)."""
    from_address = from_account.address
    balance = get_balance_wei_fn(from_address)
    gas_price = eth_gas_price(api_key, network)
    gas_limit = 21_000
    fee = gas_price * gas_limit
    if value_wei <= 0:
        raise ValueError("value_wei must be positive")
    if value_wei + fee > balance:
        raise ValueError("insufficient balance for value + gas")
    nonce = eth_get_transaction_count(from_address, api_key, network)
    tx = {
        "nonce": nonce,
        "gasPrice": gas_price,
        "gas": gas_limit,
        "to": to_address,
        "value": value_wei,
        "chainId": chain_id,
    }
    signed = from_account.sign_transaction(tx)
    raw = getattr(signed, "raw_transaction", None) or getattr(signed, "rawTransaction", None)
    hx = raw.hex() if hasattr(raw, "hex") else raw
    if not isinstance(hx, str):  # pragma: no cover — eth_account returns str/HexBytes; defensive
        hx = hx.hex()
    return eth_send_raw_transaction(hx, api_key, network)
