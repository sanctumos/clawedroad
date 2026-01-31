"""Alchemy API: balance (eth_getBalance), Prices API (ETH/USD by-symbol)."""
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
