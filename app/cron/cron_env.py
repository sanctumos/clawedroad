"""
Load .env. Python uses: MNEMONIC, ALCHEMY_API_KEY, ALCHEMY_NETWORK,
COMMISSION_WALLET_MAINNET, COMMISSION_WALLET_SEPOLIA, COMMISSION_WALLET_BASE,
and DB_DRIVER, DB_DSN, DB_USER, DB_PASSWORD for DB access.
"""
import os
from pathlib import Path

def load_dotenv(base_dir: str) -> None:
    path = Path(base_dir) / ".env"
    if not path.is_file():
        return
    with open(path) as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#"):
                continue
            if "=" not in line:
                continue
            key, _, value = line.partition("=")
            key = key.strip()
            value = value.strip().strip("'\"")
            os.environ.setdefault(key, value)

def get(key: str, default: str = "") -> str:
    return os.environ.get(key, default)

def get_required(key: str) -> str:
    v = os.environ.get(key)
    if not v:
        raise RuntimeError(f"Missing required env: {key}")
    return v
