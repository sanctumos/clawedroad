from escrow import derive_deposit_address, derive_escrow_account, derive_escrow_address


def test_derive_escrow_account_matches_address():
    mnemonic = "test test test test test test test test test test test junk"
    tx_uuid = "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
    addr = derive_escrow_address(mnemonic, tx_uuid)
    acct = derive_escrow_account(mnemonic, tx_uuid)
    assert addr.lower() == acct.address.lower()


def test_derive_deposit_distinct_from_escrow():
    mnemonic = "test test test test test test test test test test test junk"
    u = "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee"
    dep = derive_deposit_address(mnemonic, u)
    esc = derive_escrow_address(mnemonic, u)
    assert dep.lower() != esc.lower()
