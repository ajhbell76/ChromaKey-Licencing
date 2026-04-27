# ChromaKey Pro — Python App Licensing Integration Handover

## Purpose

This document is the handover pack for integrating the beta licensing system into the ChromaKey Pro Python desktop application. The WordPress plugin (`chromakey-pro-licensing`) is complete and deployed. This document covers everything needed to build the Python side.

The full specification is in `beta_wordpress_licensing_plan.md` (sections 13–19). This document distils the critical details and adds implementation notes from the completed backend.

---

## 1. What the WordPress Backend Provides

The plugin is live at `https://doctorbell.co.uk` and exposes four REST API endpoints:

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/wp-json/ckp-licensing/v1/activate` | Activate a licence on this machine |
| POST | `/wp-json/ckp-licensing/v1/validate` | Revalidate an existing activation |
| POST | `/wp-json/ckp-licensing/v1/deactivate` | Deactivate this machine |
| GET/POST | `/wp-json/ckp-licensing/v1/status` | Lightweight status check |
| GET | `/wp-json/ckp-licensing/v1/health` | API health check |

All POST endpoints expect `Content-Type: application/json`.  
All responses are JSON.

---

## 2. Configuration Constants

These should be defined in a single config file (e.g. `licensing/config.py`):

```python
LICENSING_API_BASE   = "https://doctorbell.co.uk/wp-json/ckp-licensing/v1"
PRODUCT_CODE         = "chromakey_pro"
VALIDATION_WARNING_DAYS = 5      # Show warning this many days before validation is due
CACHE_PATH           = os.path.join(os.environ["APPDATA"], "ChromaKeyPro", "licence.json")
INSTALL_ID_PATH      = os.path.join(os.environ["APPDATA"], "ChromaKeyPro", "install_id.txt")
PUBLIC_KEY_PATH      = "resources/signing_public_key.pem"  # Bundled with the app
```

---

## 3. Public Signing Key

The WordPress admin generates an RSA-2048 key pair on first install. The **public key** must be exported from the WordPress admin and bundled with the Python app.

**To get the public key:**
1. Log into WordPress admin → ChromaKey Licensing → Settings
2. Copy the PEM block shown under "Signing Key"
3. Save it to `resources/signing_public_key.pem` in the app package

The public key looks like:
```
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkq...
-----END PUBLIC KEY-----
```

**Important:** If the server key is regenerated (via the Regenerate Keys button in Settings), a new app build with the updated public key must be distributed. Existing cached licences will fail signature verification until the app is updated.

---

## 4. API Reference

### 4.1 POST /activate

Called on first launch (no local cache) or when the user manually enters their credentials.

**Request body:**
```json
{
  "email": "customer@example.com",
  "licence_key": "CKP-BETA-XXXX-XXXX-XXXX",
  "product_code": "chromakey_pro",
  "computer_name": "MY-LAPTOP",
  "device_fingerprint_hash": "<sha256 hex string>",
  "installation_id_hash": "<sha256 hex string>",
  "app_version": "1.0.0",
  "os_name": "Windows 11"
}
```

**Success response (200):**
```json
{
  "result": "valid",
  "licence_id": 1,
  "activation_id": 1,
  "email": "customer@example.com",
  "product_code": "chromakey_pro",
  "plan_code": "beta",
  "licence_status": "active",
  "activation_limit": 2,
  "expires_at": "2026-10-01T23:59:59Z",
  "server_time_utc": "2026-04-27T16:45:00Z",
  "last_validated_at": "2026-04-27T16:45:00Z",
  "next_validation_due_at": "2026-05-27T16:45:00Z",
  "grace_ends_at": "2026-06-03T16:45:00Z",
  "features": {
    "batch_processing": true,
    "export_enabled": true,
    "watermark": false
  },
  "signature": "<base64 RSA-SHA256 signature>"
}
```

**Activation limit reached (200):**
```json
{
  "result": "activation_limit_reached",
  "activation_limit": 2,
  "active_count": 2,
  "activations": [
    { "activation_id": 1, "computer_name": "STUDIO-PC", "last_validated_at": "2026-04-24T08:00:00Z" },
    { "activation_id": 2, "computer_name": "HOME-LAPTOP", "last_validated_at": "2026-04-25T11:00:00Z" }
  ]
}
```

**Error responses (4xx):**
```json
{ "result": "invalid_key",       "message": "Licence key or email is incorrect." }
{ "result": "licence_expired",   "message": "This licence has expired." }
{ "result": "licence_suspended", "message": "This licence has been suspended." }
{ "result": "licence_revoked",   "message": "This licence has been revoked." }
{ "result": "too_many_requests", "message": "Too many failed attempts. Please try again later." }
{ "result": "missing_field",     "message": "Field 'email' is required." }
```

---

### 4.2 POST /validate

Called at startup and every 30 days (or when `next_validation_due_at` has passed).

**Request body:**
```json
{
  "licence_id": 1,
  "activation_id": 1,
  "product_code": "chromakey_pro",
  "device_fingerprint_hash": "<same hash as used during activation>",
  "computer_name": "MY-LAPTOP",
  "app_version": "1.0.0",
  "os_name": "Windows 11"
}
```

**Success response (200):** Same structure as `/activate` success — a fresh signed licence grant with updated `last_validated_at` and `next_validation_due_at`.

**Error results:** `licence_expired`, `licence_suspended`, `licence_revoked`, `activation_deactivated`, `activation_revoked`, `device_mismatch`, `licence_not_found`, `activation_not_found`

---

### 4.3 POST /deactivate

Called when the user chooses Help → Licence → Deactivate This Machine.

**Request body:**
```json
{
  "licence_id": 1,
  "activation_id": 1,
  "device_fingerprint_hash": "<same hash as activation>"
}
```

**Success response (200):**
```json
{ "result": "deactivated" }
```

After success: delete the local cache file and return the app to unlicensed state.

---

### 4.4 GET /status (optional)

Lightweight check — no signature returned. Useful for the Licence Status dialog.

```
GET /wp-json/ckp-licensing/v1/status?licence_id=1&activation_id=1
```

```json
{
  "result": "active",
  "licence_status": "active",
  "expires_at": "2026-10-01T23:59:59Z",
  "activation_limit": 2,
  "active_count": 1,
  "next_validation_due_at": "2026-05-27T16:45:00Z"
}
```

---

## 5. Signature Verification

The server signs a specific subset of fields using RSA-SHA256. The Python app **must** verify this signature before trusting the cache.

**Signed fields (always these 12, alphabetical key order):**

```python
SIGNED_FIELDS = [
    "activation_id",
    "email",
    "expires_at",
    "features",
    "grace_ends_at",
    "last_validated_at",
    "licence_id",
    "licence_status",
    "next_validation_due_at",
    "plan_code",
    "product_code",
    "server_time_utc",
]
```

**Verification algorithm:**

```python
import json
import base64
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding

def verify_signature(response: dict, public_key_pem: bytes) -> bool:
    try:
        payload = {k: response[k] for k in SIGNED_FIELDS}
        # features dict must also be sorted
        if "features" in payload and isinstance(payload["features"], dict):
            payload["features"] = dict(sorted(payload["features"].items()))
        payload = dict(sorted(payload.items()))

        message = json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
        signature = base64.b64decode(response["signature"])

        public_key = serialization.load_pem_public_key(public_key_pem)
        public_key.verify(signature, message, padding.PKCS1v15(), hashes.SHA256())
        return True
    except Exception:
        return False
```

**Dependency:** `pip install cryptography`

The cache file stores the same payload + signature. On every app launch, reconstruct the payload dict from the cache and verify before trusting any values.

---

## 6. Device Identity

Two values must be generated and sent with every activate/validate call:

### Installation ID (`installation_id_hash`)
- Generate a random UUID (`uuid.uuid4()`) on first app launch
- Persist to `INSTALL_ID_PATH`
- On subsequent launches, read from file
- Hash with SHA-256 before sending: `hashlib.sha256(install_id.encode()).hexdigest()`

### Device Fingerprint (`device_fingerprint_hash`)
- Collect stable machine identifiers (see below)
- Concatenate with a fixed separator
- Hash with SHA-256

**Recommended fingerprint components on Windows:**
```python
import subprocess, hashlib

def get_device_fingerprint() -> str:
    components = []
    try:
        # Machine GUID from registry
        result = subprocess.check_output(
            ["reg", "query", r"HKLM\SOFTWARE\Microsoft\Cryptography", "/v", "MachineGuid"],
            capture_output=True, text=True
        )
        components.append(result.stdout.strip())
    except Exception:
        pass
    try:
        # Volume serial number of C:
        result = subprocess.check_output(["vol", "C:"], capture_output=True, text=True, shell=True)
        components.append(result.stdout.strip())
    except Exception:
        pass

    raw = "|".join(components) or "fallback"
    return hashlib.sha256(raw.encode()).hexdigest()
```

`computer_name` is sent separately as a display-only label (`os.environ.get("COMPUTERNAME", socket.gethostname())`). It is **not** used for identity matching on the server.

---

## 7. Local Licence Cache

**Location:** `%APPDATA%\ChromaKeyPro\licence.json`

**Format:**
```json
{
  "activation_id": 1,
  "email": "customer@example.com",
  "expires_at": "2026-10-01T23:59:59Z",
  "features": { "batch_processing": true, "export_enabled": true, "watermark": false },
  "grace_ends_at": "2026-06-03T16:45:00Z",
  "last_validated_at": "2026-04-27T16:45:00Z",
  "licence_id": 1,
  "licence_status": "active",
  "next_validation_due_at": "2026-05-27T16:45:00Z",
  "plan_code": "beta",
  "product_code": "chromakey_pro",
  "server_time_utc": "2026-04-27T16:45:00Z",
  "signature": "<base64>"
}
```

The cache file IS the signed payload from the server — save the entire response (signed fields + signature) directly. Never write to the cache without a valid signature.

**Rules:**
- If cache file is missing → `NO_LICENSE` state
- If JSON is corrupt or signature is invalid → `NO_LICENSE` state (do not crash)
- If `product_code` does not match `PRODUCT_CODE` constant → `NO_LICENSE`
- Cache is replaced on every successful activate or validate call

---

## 8. Licence State Machine

Evaluate on every app launch in this order:

| State | Condition | Behaviour |
|---|---|---|
| `NO_LICENSE` | No cache, corrupt cache, bad signature | Show activation dialog |
| `DEVICE_MISMATCH` | Cache device hash ≠ current machine | Show activation dialog |
| `REVOKED` | Server returned `licence_revoked` or `activation_revoked` | Block + message |
| `SUSPENDED` | Server returned `licence_suspended` | Block + message |
| `EXPIRED` | `expires_at` < now | Block exports + message |
| `GRACE_EXPIRED` | `grace_ends_at` < now and no internet | Block exports + require validation |
| `GRACE_PERIOD` | `next_validation_due_at` < now but `grace_ends_at` > now | Allow with warning |
| `VALIDATION_REQUIRED` | `next_validation_due_at` <= now, online | Validate before enabling exports |
| `VALIDATION_DUE_SOON` | `next_validation_due_at` within `VALIDATION_WARNING_DAYS` | Allow with subtle warning |
| `VALID` | Everything passes | Full access |

**Startup sequence:**
```
1. Load cache → check signature → check product code
2. Check device fingerprint matches
3. Check expires_at
4. Check grace_ends_at
5. If online → attempt background validation
6. On validation success → update cache → clear any warnings
7. Apply resulting state → gate features accordingly
```

---

## 9. Feature Gating

The `features` dict in the licence payload controls app behaviour:

| Key | Type | Effect |
|---|---|---|
| `export_enabled` | bool | If false, disable all JPG/PNG export |
| `watermark` | bool | If true, burn a watermark onto exported images |
| `batch_processing` | bool | If false, disable hot-folder / batch mode |

For beta, the server returns `export_enabled: true, watermark: false, batch_processing: true` for all active licences. Gate the export function as the primary enforcement point — if the licence state is anything other than `VALID`, `VALIDATION_DUE_SOON`, or `GRACE_PERIOD`, disable export.

---

## 10. User-Facing Error Messages

Use these exact strings to keep messaging consistent:

| Condition | Message |
|---|---|
| `invalid_key` | Licence key or email is incorrect. Please check and try again. |
| `licence_expired` | This licence has expired. Please contact support. |
| `licence_suspended` | This licence has been suspended. Please contact support. |
| `licence_revoked` | This licence has been revoked. Please contact support. |
| `activation_limit_reached` | This licence has reached its activation limit. |
| `activation_deactivated` | This machine has been deactivated. Please reactivate to continue. |
| `device_mismatch` | This licence does not match this machine. Please reactivate. |
| `too_many_requests` | Too many failed attempts. Please wait before trying again. |
| Server unreachable | Could not reach the licensing server. |
| Grace period active | Licence validation is overdue. Please connect to the internet to validate. You can continue until {grace_ends_at}. |
| Grace expired | Licence validation is overdue and export has been disabled. Please connect to the internet to validate. |
| Invalid signature | Licence data is invalid. Please reactivate. |

---

## 11. UI Requirements

### Activation Dialog
- Shown when app state is `NO_LICENSE`, `DEVICE_MISMATCH`, or `ACTIVATION_DEACTIVATED`
- Fields: Email address, Licence key
- Buttons: Activate, (link) Contact Support
- On success: dismiss dialog, enter licensed mode
- On `activation_limit_reached`: show the list of active machines from the server response

### Licence Status Dialog (Help → Licence Status)
```
Licensed to:        customer@example.com
Plan:               Beta
Status:             Active
Expires:            1 October 2026
Last validated:     27 April 2026
Next validation:    27 May 2026
This machine:       MY-LAPTOP
```
Buttons: Validate Now, Deactivate This Machine, Close

### Menu Location
```
Help
  └── Licence Status
  └── Deactivate This Machine
```

---

## 12. Recommended Module Structure

```
src/licensing/
    __init__.py
    config.py              # Constants (API URL, product code, paths)
    device_identity.py     # Installation ID + device fingerprint
    licence_cache.py       # Read / write / clear local cache
    signature_verifier.py  # RSA-SHA256 verification
    licensing_client.py    # HTTP calls to all 4 endpoints
    licence_state.py       # State evaluation logic
    licensing_errors.py    # Custom exception types

src/ui/licensing/
    activation_dialog.py
    licence_status_dialog.py
    activation_limit_dialog.py
    validation_warning_dialog.py
```

---

## 13. Build Phases (from original plan)

| Phase | Deliverable |
|---|---|
| APP-1 | Config constants, public key bundled |
| APP-2 | `licence_cache.py` — read/write/clear |
| APP-3 | `signature_verifier.py` — verify signed payload |
| APP-4 | `device_identity.py` — install ID + fingerprint |
| APP-5 | `licensing_client.py` — all 4 API calls |
| APP-6 | Activation dialog + flow |
| APP-7 | Startup state check integrated into main app |
| APP-8 | Grace period handling + export restriction |
| APP-9 | Activation limit dialog |
| APP-10 | Deactivate this machine (menu + API call) |

---

## 14. Admin Contact

Licences are managed at `https://doctorbell.co.uk/wp-admin` under **ChromaKey Licensing**.

Beta testers who hit the activation limit should contact the admin to have a machine deactivated from the Activations screen. Admin deactivation frees the slot immediately — the user can then reactivate on their new machine.

---

## 15. Key Technical Notes

- **All datetime fields are UTC ISO 8601** (`2026-05-27T16:45:00Z`). Parse with `datetime.fromisoformat()` (strip trailing Z first on Python < 3.11) or `datetime.strptime(s, "%Y-%m-%dT%H:%M:%SZ")`.
- **The rate limiter** blocks an IP after 10 failed attempts per hour. During development, test against a local copy or use a VPN between tests to avoid locking yourself out.
- **Same-machine reactivation:** if the same `device_fingerprint_hash` + `installation_id_hash` pair activates again while the activation is still active, the server reuses the existing record rather than consuming a new slot. This handles the case where the user reinstalls the app.
- **Background validation** should be non-blocking — run in a thread and only update the UI if the result changes the state.
- **Never store the raw licence key** in the cache or logs. The user enters it once; it is sent to the server and then discarded.
- **Exported Signing Key from the plugin settings page** :
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA2VTzBxI+gUAmXSQ+H7RK
yE2158QezjIGN2D9HKPqlmjuhip874djdVnCWVBvmADXbfUXyWAslruL/weWm7Uc
cp4tFtik69Cu80lMIZeODmPuECObIf0AmaYG9Fx40X3Zvtc/26jIEi8kQYu3H+GT
OWK8aMZcfcjP7vhPzOG/fuQ2e5uHs7KWgl5QpB3r2syDfXb3fZA5P1l5Bs9YfTrn
h8POCOJ8dvXJ5MO/11TXWrT3TfjDO4rDvGo3SF66NLQFKZXUVDtPayWvOPyFxfTs
MKxaZPqtokEE3KfKtQhbFbef667bsfuzpFJzf+ud1rhr6fw5c4C6sZhb5XgOKCKt
mQIDAQAB
-----END PUBLIC KEY-----

