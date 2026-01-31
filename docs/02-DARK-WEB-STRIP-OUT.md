# Dark-Web Strip-Out

What to remove or replace so the product is a clearnet marketplace with no dark-web–specific features.

**Binding decisions:** See **08-PLANNING-DECISIONS-QA.md** (vendorship = “I agree” + DB field; messages = no E2E in MVP).

---

## 1. PGP and Encrypted Message Signing

### 1.1 Remove Entirely

- **User/Store PGP keys**: No PGP public key storage, validation, or display.
- **PGP login**: No “Login with PGP” flow (decrypt challenge with private key).
- **PGP 2FA**: No PGP-based second factor for API or web.
- **Vendorship agreement signing**: No “sign agreement with PGP” step. **Replace with “I agree”** — store acceptance in a **DB field** (e.g. agreed_at timestamp, user_id, agreement_version). No PDF (08).
- **Encrypted messaging**: No PGP-encrypted messages in transaction threads or messageboard. **Plain text only** (over HTTPS). **No E2E encryption** in MVP (08).
- **Signature verification**: No `util.CheckSignature`, `util.EncryptText`, `util.ValidatePGPPublicKey`, `util.Fingerprint`, etc. for user-facing flows.

### 1.2 Code Locations (Current)

| Area | File(s) | What to strip / replace |
|------|---------|-------------------------|
| PGP util | `modules/util/pgp.go` | Remove or stub; no PGP in new stack. |
| User model | `modules/marketplace/models_user.go` | Remove `Pgp` field, PGP validation, PGPFingerprint, 2FA-by-PGP checks. |
| Store model | `modules/marketplace/models_store.go` | Remove `PGP`, `VendorshipAgreementSignatureDate`, `VendorshipAgreementText` PGP signing; HasSignedVendorshipAgreement() → **“I agree” stored in DB** (e.g. agreed_at, user_id). Per 08. |
| Auth views | `modules/marketplace/views_auth.go` | Remove LoginPGP*, SetupPGPViaDecryptionStep1/2, PGP setup; “User doesn't have PGP set up” error. |
| API settings | `modules/marketplace/views_api.go` | Remove ViewAPISetupPGPViaDecryptionStep1/2 POST. |
| Router | `modules/marketplace/router.go` | Remove `/settings/user/pgp/step1`, `step2`, `/api/.../pgp/step1`, `step2`. |
| Context | `modules/marketplace/context.go` | Remove `Pgp` from context. |
| Messageboard | `modules/marketplace/models_messageboard_view_model.go` | Remove PGP message detection (-----BEGIN PGP MESSAGE----- etc.); treat as plain text. |
| Localization | `modules/marketplace/localization.go` | Remove LoginWithPGP, PGPPublicKey, SetupPGPKey. |
| Templates | `templates/auth/` | Remove pgplogin, settings_pgp_signature_step_1/2. |

### 1.3 API Session 2FA

- Current: `APISession.IsTwoFactorSession`, `IsSecondFactorCompleted`, `SecondFactorSecretText` can drive PGP 2FA. For U/P-only: either remove 2FA or replace with TOTP (not in scope here; just drop PGP 2FA).

---

## 2. Tor / Onion References

### 2.1 Import Paths

- All Go imports use `qxklmrhx7qkzais6.onion/Tochka/tochka-free-market/...`. This is an **onion URL as module path** (for Tor-based go get). For the **new** stack (PHP/Python) there are no Go imports; for any retained Go tooling, replace with a clearnet or local module path (e.g. `github.com/yourorg/store` or `store/...`).

### 2.2 Copy and Config

- **README.md**: “DarkNet operations”, “DarkNet Market (DNM)”, “Tochka”, “torsocks go get”, “torify git clone”. Replace with neutral marketplace wording; no Tor install instructions.
- **settings.json.example**: No Tor-specific keys (current file has none; keep SiteURL/SiteName generic).
- **Static/templates**: Any “onion”, “Tor”, “darknet” in user-facing strings — remove or replace.

---

## 3. Dark-Web–Specific Product Wording

- **Site name / branding**: “Tochka Free Market” → your product name; remove “free marketplace for DarkNet operations”.
- **Docs/help**: `static/en/`, `templates/`, `data/i18n/*.json` — strip references to Tor, PGP requirement, “stay safe” dark-web advice (or generalize to “security tips”).
- **Vendorship agreement**: `data/i18n/EN_vendorship_agreement.txt` — rewrite for clearnet; remove PGP signing requirement.

---

## 4. Optional: Bot Check / Captcha

- Current: Bot check + captcha to limit abuse (e.g. registration). Not inherently “dark-web”; keep or simplify (e.g. standard captcha only) per product needs.

---

## 5. Messaging and Privacy

- **Messageboard / PM**: No PGP encryption of content. Messages stored as plain text (over HTTPS). No “PGP required” for vendors or buyers.
- **Transaction thread**: Shipping address and messages — plain text; no encrypted signing of messages.

---

## 6. Checklist for Strip-Out (When Porting)

- [ ] Remove PGP from user and store models and APIs.
- [ ] Remove all PGP login, PGP 2FA, and PGP setup routes and views.
- [ ] Replace vendorship agreement flow with simple accept (no PGP signature).
- [ ] Remove PGP detection/formatting in messageboard/PM.
- [ ] Replace onion module path with clearnet path if any Go remains.
- [ ] Update README, help, and i18n to clearnet wording; remove Tor instructions.
- [ ] Drop dependency on `golang.org/x/crypto/openpgp` (and related) for app code.

This gives a clean list to implement when moving to PHP/MariaDB + Python so that no dark-web or PGP behavior remains.
