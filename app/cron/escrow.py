"""
HD-derived escrow address (BIP-32/44) per transaction.
Deterministic: same transaction_uuid always yields same address.
"""
import hashlib
from eth_account import Account

Account.enable_unaudited_hdwallet_features()

def _derivation_index(transaction_uuid: str) -> int:
    """Deterministic index from transaction uuid (0 .. 2^31-1)."""
    h = hashlib.sha256(transaction_uuid.encode()).hexdigest()[:8]
    return int(h, 16) % (2**31)

def derive_escrow_address(mnemonic: str, transaction_uuid: str) -> str:
    """
    Derive EVM escrow address for a transaction.
    Path: m/44'/60'/0'/0/{index} where index = f(transaction_uuid).
    """
    index = _derivation_index(transaction_uuid)
    path = f"m/44'/60'/0'/0/{index}"
    acct = Account.from_mnemonic(mnemonic, account_path=path)
    return acct.address


def derive_deposit_address(mnemonic: str, deposit_uuid: str) -> str:
    """
    Derive EVM address for a deposit (v2.5). Cron fills deposits.address when NULL.
    Path: m/44'/60'/0'/1/{index} where index = f(deposit_uuid). Separate from escrow (0'/0).
    """
    index = _derivation_index(deposit_uuid)
    path = f"m/44'/60'/0'/1/{index}"
    acct = Account.from_mnemonic(mnemonic, account_path=path)
    return acct.address
