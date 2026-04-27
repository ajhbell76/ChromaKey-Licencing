# Beta Licensing System Plan  
## WordPress Plugin and Python Application Integration

## 1. Purpose

This document defines the beta implementation plan for adding a licensing system to a Python-based desktop product, using an existing HTTPS-enabled WordPress site as the temporary licensing backend.

The beta approach is intended to reduce hosting and infrastructure costs while still providing a structured, auditable and secure licensing model.

The proposed model is:

```text
WordPress custom licensing plugin
↓
Licensing REST API
↓
Admin-created beta licences
↓
Python desktop app activation
↓
Local signed licence cache
↓
30-day revalidation cycle
```

The WordPress server is the source of truth. The desktop app should cache licence information locally for offline use, but should not be trusted as the authority.

---

## 2. Beta Licensing Approach

For beta, the recommended approach is to build a custom WordPress plugin that provides:

- Custom licensing database tables
- WordPress admin screens for licence management
- REST API endpoints for the Python app
- Activation tracking
- Licence validation
- Licence expiry handling
- Signed licence responses
- Audit logging

The Python app will call the WordPress REST API to activate and validate licences.

---

## 3. Key Beta Decisions

| Area | Beta Decision |
|---|---|
| Backend | Existing WordPress site |
| Hosting | Existing HTTPS-enabled web host |
| Delivery | Custom installable WordPress plugin |
| Database | Custom plugin tables inside the WordPress database |
| Activation method | Email address + licence key |
| Customer WordPress login | Not required for beta |
| Admin UI | WordPress admin pages |
| User self-service portal | Future phase |
| API | WordPress REST API |
| Validation interval | 30 days |
| Grace period | 7 days |
| Local licence cache | Signed JSON file |
| Signing model | Server private key, app public key |
| Enforcement point | Disable export if licence is invalid |
| Machine display | Computer name |
| Machine identity | Hashed device fingerprint + installation ID |

---

## 4. Beta Scope

### 4.1 Included in Beta

The beta plugin should provide:

| Area | Requirement |
|---|---|
| Admin licence creation | Internal users can create beta licences |
| Email + licence key activation | Desktop app activates using email and key |
| Activation limit | Licence can allow a fixed number of machines |
| Expiry date | Licence expires after the beta period |
| Machine tracking | Store activated computer names and device identities |
| 30-day validation | App must check back with server |
| Grace period | App can continue briefly after validation is overdue |
| Admin deactivation | Admin can deactivate a machine |
| Current-machine deactivation | App can deactivate itself |
| Audit logging | Important changes are recorded |
| Signed licence response | App can verify local cache was issued by the server |

### 4.2 Excluded from Beta

The following should not be included in the first beta release:

| Excluded Item | Reason |
|---|---|
| Payment system | Not required for beta |
| Stripe integration | Future commercial phase |
| Full customer portal | Add later if needed |
| WordPress customer accounts | Email + licence key is simpler |
| Organisation/team licensing | Future enhancement |
| Floating concurrent licences | Future enhancement |
| Offline manual activation files | Future enhancement |
| Subscription renewal automation | Future commercial phase |

---

## 5. WordPress Plugin Overview

### 5.1 Plugin Name

Working name:

```text
ChromaKey Pro Licensing
```

### 5.2 Plugin Folder

```text
chromakey-pro-licensing/
```

### 5.3 Main Plugin File

```text
chromakey-pro-licensing.php
```

### 5.4 REST API Namespace

```text
/wp-json/ckp-licensing/v1/
```

### 5.5 Product Code

```text
chromakey_pro
```

---

## 6. WordPress Plugin Architecture

Recommended plugin structure:

```text
chromakey-pro-licensing/
│
├── chromakey-pro-licensing.php
│
├── includes/
│   ├── class-ckp-activator.php
│   ├── class-ckp-db.php
│   ├── class-ckp-admin-menu.php
│   ├── class-ckp-admin-customers.php
│   ├── class-ckp-admin-licences.php
│   ├── class-ckp-admin-activations.php
│   ├── class-ckp-rest-api.php
│   ├── class-ckp-licence-service.php
│   ├── class-ckp-activation-service.php
│   ├── class-ckp-validation-service.php
│   ├── class-ckp-signing-service.php
│   ├── class-ckp-audit-service.php
│   └── class-ckp-settings.php
│
├── admin/
│   ├── views/
│   │   ├── dashboard.php
│   │   ├── customers-list.php
│   │   ├── customer-edit.php
│   │   ├── licence-edit.php
│   │   ├── activations-list.php
│   │   ├── audit-log.php
│   │   └── settings.php
│   │
│   └── assets/
│       ├── admin.css
│       └── admin.js
│
└── uninstall.php
```

The plugin should be installable through the WordPress admin plugin upload screen.

---

## 7. Database Design

The plugin should use custom database tables with the WordPress table prefix.

Example table names:

```text
wp_ckp_accounts
wp_ckp_licences
wp_ckp_activations
wp_ckp_validation_log
wp_ckp_audit_log
wp_ckp_settings
```

WordPress core tables should not be modified.

---

### 7.1 `wp_ckp_accounts`

Stores beta customer identity.

| Field | Type | Notes |
|---|---:|---|
| id | bigint | Primary key |
| email | varchar | Unique customer email |
| display_name | varchar | Optional |
| company_name | varchar | Optional |
| status | varchar | active / disabled |
| notes | text | Internal admin notes |
| created_at | datetime | Created timestamp |
| updated_at | datetime | Updated timestamp |

For beta, the account does not need to be linked to a WordPress user.

---

### 7.2 `wp_ckp_licences`

Stores licence entitlement.

| Field | Type | Notes |
|---|---:|---|
| id | bigint | Primary key |
| account_id | bigint | Linked account |
| product_code | varchar | Example: `chromakey_pro` |
| licence_key_hash | varchar | Hashed licence key |
| licence_key_last4 | varchar | Display helper |
| plan_code | varchar | beta / trial / pro |
| status | varchar | active / expired / suspended / revoked |
| activation_limit | int | Number of allowed machines |
| validation_interval_days | int | Default 30 |
| grace_period_days | int | Default 7 |
| starts_at | datetime | Licence start date |
| expires_at | datetime | Licence expiry date |
| created_by_user_id | bigint | WordPress admin user ID |
| created_at | datetime | Created timestamp |
| updated_at | datetime | Updated timestamp |

The raw licence key should only be shown once when generated.

Example key format:

```text
CKP-BETA-9F4K-22QD-M7PA
```

After creation, only a masked version should be shown:

```text
CKP-BETA-****-****-M7PA
```

---

### 7.3 `wp_ckp_activations`

Stores activated machines.

| Field | Type | Notes |
|---|---:|---|
| id | bigint | Primary key |
| licence_id | bigint | Linked licence |
| account_id | bigint | Linked account |
| device_fingerprint_hash | varchar | Machine identity hash |
| installation_id_hash | varchar | App install identity hash |
| computer_name | varchar | Friendly display name |
| os_name | varchar | Windows 11, macOS etc |
| app_version | varchar | Desktop app version |
| status | varchar | active / deactivated / revoked |
| first_activated_at | datetime | First activation date |
| last_validated_at | datetime | Last successful validation |
| next_validation_due_at | datetime | Last validation + interval |
| deactivated_at | datetime | Nullable |
| deactivated_reason | varchar | Optional |
| created_at | datetime | Created timestamp |
| updated_at | datetime | Updated timestamp |

Important rule:

```text
Computer name is not the machine identity.
```

Computer name should be used for display only. The actual activation identity should use the hashed device fingerprint and installation ID.

---

### 7.4 `wp_ckp_validation_log`

Stores app validation attempts.

| Field | Type | Notes |
|---|---:|---|
| id | bigint | Primary key |
| licence_id | bigint | Nullable if failed early |
| activation_id | bigint | Nullable if failed early |
| email | varchar | Submitted email |
| result | varchar | success / failed |
| reason | varchar | expired / revoked / limit_reached etc |
| product_code | varchar | Product code |
| app_version | varchar | App version |
| ip_address | varchar | Optional |
| created_at | datetime | Timestamp |

This table supports troubleshooting during beta.

---

### 7.5 `wp_ckp_audit_log`

Stores admin and system actions.

| Field | Type | Notes |
|---|---:|---|
| id | bigint | Primary key |
| actor_type | varchar | admin / app / system |
| actor_id | bigint | WordPress user ID or null |
| action | varchar | licence_created, activation_deactivated etc |
| entity_type | varchar | account / licence / activation |
| entity_id | bigint | Target record |
| old_value_json | longtext | Optional |
| new_value_json | longtext | Optional |
| created_at | datetime | Timestamp |

---

### 7.6 `wp_ckp_settings`

Stores plugin settings.

| Field | Type | Notes |
|---|---:|---|
| id | bigint | Primary key |
| setting_key | varchar | Unique setting key |
| setting_value | longtext | Setting value |

Recommended settings:

| Setting | Beta Default |
|---|---|
| Product code | `chromakey_pro` |
| Default activation limit | `2` |
| Default validation interval | `30` |
| Default grace period | `7` |
| Signing private key | Generated by plugin |
| Signing public key | Exported for application build |
| API rate limit | Enabled |
| Admin notification email | Support/admin email |

---

## 8. WordPress Admin Area

The plugin should add a WordPress admin menu:

```text
ChromaKey Licensing
    Dashboard
    Customers
    Licences
    Activations
    Audit Log
    Settings
```

---

### 8.1 Dashboard

The dashboard should provide a quick operational view.

Suggested widgets:

| Widget | Purpose |
|---|---|
| Active beta licences | Number of usable licences |
| Expiring in 14 days | Licences needing review |
| Total active activations | Machines currently activated |
| Failed activation attempts | Support and abuse signal |
| Recent validations | Confirms app check-ins |
| Revoked/suspended licences | Control visibility |

---

### 8.2 Customers

Admin users should be able to:

- Add customer
- Search by email
- View linked licences
- View internal notes
- Disable account

Customer fields:

```text
Email
Display name
Company name
Status
Internal notes
```

---

### 8.3 Licences

Admin users should be able to:

- Create licence
- Assign licence to customer email
- Generate licence key
- Set activation limit
- Set expiry date
- Set validation interval
- Set grace period
- Suspend licence
- Revoke licence
- Extend licence
- View activations
- View validation history

Licence creation form:

```text
Customer email
Product
Plan
Activation limit
Start date
Expiry date
Status
Internal note
```

After saving, the raw generated licence key should be shown once with a copy button.

---

### 8.4 Activations

Admin users should be able to view:

| Field | Example |
|---|---|
| Customer | `customer@example.com` |
| Licence | `CKP-BETA-****-M7PA` |
| Computer | `EVENT-LAPTOP` |
| App version | `0.1.1` |
| OS | `Windows 11` |
| First activated | Date/time |
| Last validated | Date/time |
| Next validation due | Date/time |
| Status | active |

Admin actions:

- Deactivate machine
- Revoke activation
- Add note
- View validation log

Difference between deactivate and revoke:

| Action | Meaning |
|---|---|
| Deactivate | Frees the slot; user may reactivate |
| Revoke | Blocks that activation/device unless admin allows |

---

### 8.5 Audit Log

The audit log should support filtering by:

- Customer email
- Licence ID
- Activation ID
- Admin user
- Action
- Date range

Example audit actions:

```text
licence_created
licence_extended
licence_suspended
licence_revoked
activation_created
activation_deactivated
activation_revoked
validation_failed
settings_changed
```

---

### 8.6 Settings

The settings screen should include:

| Setting | Notes |
|---|---|
| Product code | Keep stable once app uses it |
| Default beta expiry | Optional |
| Default activation limit | Usually 2 |
| Validation interval | 30 days |
| Grace period | 7 days |
| API enabled | Kill switch |
| Debug logging | Off by default |
| Signing key status | Show whether key exists, not the key itself |

---

## 9. REST API Design

The WordPress plugin should expose REST API endpoints for the Python desktop app.

Base namespace:

```text
/wp-json/ckp-licensing/v1/
```

The endpoints should validate all input and return safe, structured error responses.

---

### 9.1 Activate Licence

```text
POST /wp-json/ckp-licensing/v1/activate
```

Purpose:

Activate a licence on a machine.

Request:

```json
{
  "email": "customer@example.com",
  "licence_key": "CKP-BETA-9F4K-22QD-M7PA",
  "product_code": "chromakey_pro",
  "computer_name": "EVENT-LAPTOP",
  "device_fingerprint_hash": "abc123",
  "installation_id_hash": "xyz789",
  "app_version": "0.1.1",
  "os_name": "Windows 11"
}
```

Validation rules:

1. Email is required.
2. Licence key is required.
3. Product code must match.
4. Licence must exist.
5. Licence key hash must match.
6. Licence status must be active.
7. Licence must be within start/end dates.
8. If the same machine is already active, reuse the activation.
9. If active machine count is below the limit, create activation.
10. If the limit is reached, reject activation and return active machine list.

Success response:

```json
{
  "result": "valid",
  "licence_id": 1001,
  "activation_id": 501,
  "email": "customer@example.com",
  "product_code": "chromakey_pro",
  "plan_code": "beta",
  "licence_status": "active",
  "activation_limit": 2,
  "expires_at": "2026-07-27T23:59:59Z",
  "server_time_utc": "2026-04-27T16:45:00Z",
  "last_validated_at": "2026-04-27T16:45:00Z",
  "next_validation_due_at": "2026-05-27T16:45:00Z",
  "grace_ends_at": "2026-06-03T16:45:00Z",
  "features": {
    "export_enabled": true,
    "watermark": false,
    "batch_processing": true
  },
  "signature": "SERVER_SIGNATURE"
}
```

Activation limit reached response:

```json
{
  "result": "activation_limit_reached",
  "activation_limit": 2,
  "active_count": 2,
  "activations": [
    {
      "activation_id": 501,
      "computer_name": "STUDIO-PC",
      "last_validated_at": "2026-04-24T08:00:00Z"
    },
    {
      "activation_id": 502,
      "computer_name": "EVENT-LAPTOP",
      "last_validated_at": "2026-04-25T11:00:00Z"
    }
  ]
}
```

---

### 9.2 Validate Activation

```text
POST /wp-json/ckp-licensing/v1/validate
```

Purpose:

Recheck the current activation.

Request:

```json
{
  "licence_id": 1001,
  "activation_id": 501,
  "product_code": "chromakey_pro",
  "device_fingerprint_hash": "abc123",
  "installation_id_hash": "xyz789",
  "computer_name": "EVENT-LAPTOP",
  "app_version": "0.1.1",
  "os_name": "Windows 11"
}
```

Validation rules:

1. Licence exists.
2. Activation exists.
3. Activation belongs to licence.
4. Product code matches.
5. Device fingerprint matches expected activation.
6. Licence is active.
7. Licence has not expired.
8. Activation is active.
9. Server updates `last_validated_at`.
10. Server returns new signed licence grant.

Possible failure results:

```text
licence_expired
licence_suspended
licence_revoked
activation_deactivated
activation_revoked
device_mismatch
licence_not_found
server_error
```

---

### 9.3 Deactivate Current Machine

```text
POST /wp-json/ckp-licensing/v1/deactivate
```

Purpose:

Allow the app to deactivate the current machine.

Request:

```json
{
  "licence_id": 1001,
  "activation_id": 501,
  "device_fingerprint_hash": "abc123"
}
```

Rules:

1. Activation must exist.
2. Device fingerprint must match.
3. Activation becomes deactivated.
4. Activation no longer counts against the activation limit.
5. Audit record is written.

Response:

```json
{
  "result": "deactivated"
}
```

---

### 9.4 Status Check

```text
POST /wp-json/ckp-licensing/v1/status
```

Purpose:

Optional lightweight endpoint for the app to show licence state.

Request:

```json
{
  "licence_id": 1001,
  "activation_id": 501
}
```

Response:

```json
{
  "result": "active",
  "expires_at": "2026-07-27T23:59:59Z",
  "activation_limit": 2,
  "active_count": 1,
  "next_validation_due_at": "2026-05-27T16:45:00Z"
}
```

This endpoint is optional for beta because validation already returns most of the required data.

---

## 10. Licence Signing

The server should return a signed licence grant.

Recommended signing model:

```text
Private key lives on the WordPress server
Public key ships with the desktop app
```

The server signs the licence payload. The desktop app verifies the signature using the public key.

This prevents the local licence cache being edited to extend dates or enable features.

---

### 10.1 Signed Payload Contents

The signed payload should include:

```json
{
  "licence_id": 1001,
  "activation_id": 501,
  "email": "customer@example.com",
  "product_code": "chromakey_pro",
  "plan_code": "beta",
  "licence_status": "active",
  "expires_at": "2026-07-27T23:59:59Z",
  "server_time_utc": "2026-04-27T16:45:00Z",
  "last_validated_at": "2026-04-27T16:45:00Z",
  "next_validation_due_at": "2026-05-27T16:45:00Z",
  "grace_ends_at": "2026-06-03T16:45:00Z",
  "features": {
    "export_enabled": true,
    "watermark": false,
    "batch_processing": true
  }
}
```

The signature must cover the payload. The app should reject edited payloads.

---

## 11. WordPress Plugin Security Rules

Minimum beta security requirements:

| Area | Requirement |
|---|---|
| HTTPS | Required |
| Licence keys | Store hash only |
| Admin access | WordPress admin capability required |
| API input | Validate and sanitise all input |
| API abuse | Rate limit activation attempts |
| Secrets | Do not display private keys in admin UI |
| Audit | Log licence and activation changes |
| Errors | Return safe messages, not PHP stack traces |
| Backups | Database backup before install/update |
| Updates | Keep WordPress, PHP and plugins patched |

### 11.1 Admin Capability

Only users with a specific capability should manage licences:

```text
manage_ckp_licensing
```

During beta, this can be mapped to WordPress administrators only.

Later, a custom role can be added:

```text
CKP Licence Manager
```

---

## 12. WordPress Plugin Build Phases

### Phase WP-1: Plugin Skeleton

Deliverables:

- Installable plugin ZIP
- Main plugin file
- Plugin header
- Activation hook
- Admin menu placeholder
- Settings page placeholder

Acceptance criteria:

- Plugin installs from WordPress admin.
- Plugin activates without errors.
- Menu item appears in admin.
- Deactivation does not delete data.

---

### Phase WP-2: Database Install

Deliverables:

- Custom tables created on activation
- Plugin database version stored
- Safe upgrade path for future schema changes

Acceptance criteria:

- Tables are created with the WordPress table prefix.
- Re-activating plugin does not duplicate tables.
- Existing data remains after plugin deactivate/reactivate.
- Plugin records current schema version.

---

### Phase WP-3: Admin Customer and Licence Management

Deliverables:

- Customer list
- Create/edit customer
- Licence list
- Create/edit licence
- Generate licence key
- Store licence key hash
- Display raw key once only

Acceptance criteria:

- Admin can create customer by email.
- Admin can generate a beta licence.
- Admin can set expiry date.
- Admin can set activation limit.
- Admin can suspend/revoke licence.
- Audit log records changes.

---

### Phase WP-4: Activation API

Deliverables:

```text
POST /activate
```

Acceptance criteria:

- Valid email/key activates successfully.
- Invalid key is rejected.
- Expired licence is rejected.
- Suspended/revoked licence is rejected.
- Activation limit is enforced.
- Same machine does not consume an extra activation.
- Activation result is logged.
- Response includes signed licence grant.

---

### Phase WP-5: Validation API

Deliverables:

```text
POST /validate
```

Acceptance criteria:

- Active activation validates successfully.
- Last validated date is updated.
- Next validation due date is updated.
- Expired licence is rejected.
- Revoked activation is rejected.
- Device mismatch is rejected.
- New signed licence grant is returned.

---

### Phase WP-6: Deactivation API

Deliverables:

```text
POST /deactivate
```

Acceptance criteria:

- Current machine can deactivate itself.
- Device mismatch cannot deactivate another machine.
- Deactivated machine no longer counts against activation limit.
- Audit log records deactivation.

---

### Phase WP-7: Admin Activation Management

Deliverables:

- View activations by licence/customer
- Deactivate activation
- Revoke activation
- View validation history

Acceptance criteria:

- Admin can see active machines.
- Admin can deactivate a machine.
- Admin can revoke suspicious activation.
- Admin can see last validation date.
- Admin can see app version and OS.

---

### Phase WP-8: Beta Hardening

Deliverables:

- Rate limiting
- Cleaner error responses
- Admin audit filters
- Optional log export as CSV
- Basic health check endpoint
- Backup/restore notes

Acceptance criteria:

- Repeated invalid activation attempts are throttled.
- API never exposes raw database/PHP errors.
- Admin can troubleshoot beta users from logs.
- Plugin survives normal WordPress updates.

---

# 13. Python Application Implementation Plan

Once the WordPress plugin is built, the Python application needs a licensing layer.

Licensing should be separated from the main product logic.

---

## 13.1 Recommended Application Structure

```text
src/
│
├── licensing/
│   ├── __init__.py
│   ├── licensing_client.py
│   ├── license_cache.py
│   ├── license_models.py
│   ├── license_state.py
│   ├── device_identity.py
│   ├── signature_verifier.py
│   └── licensing_errors.py
│
├── ui/
│   ├── licensing/
│   │   ├── activation_dialog.py
│   │   ├── license_status_dialog.py
│   │   ├── activation_limit_dialog.py
│   │   └── validation_warning_dialog.py
│
└── main.py
```

---

## 13.2 Application Licence States

The app should recognise these states:

| State | Meaning | App Behaviour |
|---|---|---|
| `NO_LICENSE` | No local cache | Show activation |
| `VALID` | Licence valid | Full access |
| `VALIDATION_DUE_SOON` | Due within warning window | Full access + warning |
| `VALIDATION_REQUIRED` | Due now | Try online validation |
| `GRACE_PERIOD` | Validation overdue but grace remains | Allow access + warning |
| `GRACE_EXPIRED` | Too long since validation | Block app or exports |
| `EXPIRED` | Licence expiry passed | Block app or exports |
| `SUSPENDED` | Server suspended licence | Block |
| `REVOKED` | Server revoked licence | Block |
| `ACTIVATION_DEACTIVATED` | This machine removed | Require reactivation |
| `DEVICE_MISMATCH` | Cache does not match machine | Require activation |

For beta, the recommended enforcement is to disable export rather than blocking the entire application.

---

## 13.3 First-Launch Activation Flow

```text
App starts
↓
Check for local licence cache
↓
No cache found
↓
Show activation dialog
↓
User enters email + licence key
↓
App generates/sends device identity
↓
WordPress API validates licence
↓
Server returns signed licence grant
↓
App verifies signature
↓
App stores local cache
↓
App enters licensed mode
```

Activation dialog fields:

```text
Email address
Licence key
Activate button
```

Optional fields/actions:

```text
Open support link
Paste from clipboard
```

Successful activation message:

```text
ChromaKey Pro has been activated on this machine.
```

---

## 13.4 Activation Failure Messages

| Server Result | App Message |
|---|---|
| `invalid_key` | Licence key or email is incorrect. |
| `licence_expired` | This licence has expired. |
| `licence_suspended` | This licence has been suspended. |
| `activation_limit_reached` | This licence has reached its activation limit. |
| `server_unavailable` | Licensing server could not be reached. |

---

## 13.5 App Launch Validation Flow

Every app launch should run:

```text
Load local licence cache
↓
Verify licence signature
↓
Check product code
↓
Check activation/device identity
↓
Check expiry date
↓
Check validation due date
↓
If online, attempt background validation
↓
Apply resulting licence state
```

Expected behaviour:

| Situation | Behaviour |
|---|---|
| Valid cache, validation not due | Start app normally |
| Valid cache, online | Quietly validate in background |
| Valid cache, validation due soon | Start app, show subtle warning |
| Validation due and online | Validate before enabling exports |
| Validation due and offline | Enter grace period if allowed |
| Grace expired | Require internet validation |
| Signature invalid | Reject cache and require activation |

---

## 13.6 Local Licence Cache

Recommended location:

```text
%APPDATA%\ChromaKeyPro\licence.json
```

Example cache:

```json
{
  "payload": {
    "licence_id": 1001,
    "activation_id": 501,
    "email": "customer@example.com",
    "product_code": "chromakey_pro",
    "plan_code": "beta",
    "licence_status": "active",
    "expires_at": "2026-07-27T23:59:59Z",
    "server_time_utc": "2026-04-27T16:45:00Z",
    "last_validated_at": "2026-04-27T16:45:00Z",
    "next_validation_due_at": "2026-05-27T16:45:00Z",
    "grace_ends_at": "2026-06-03T16:45:00Z",
    "features": {
      "export_enabled": true,
      "watermark": false,
      "batch_processing": true
    }
  },
  "signature": "SERVER_SIGNATURE"
}
```

The app must not trust the cache unless the signature verifies.

---

## 13.7 Device Identity

The app should generate two values.

### Installation ID

Created once on first app run:

```text
install_id
```

Purpose:

- Identifies this app installation.
- Helps avoid duplicate activations.
- Changes if app is fully wiped/reinstalled.

### Device Fingerprint

Generated from stable machine data and hashed before sending.

Potential components:

```text
Machine GUID
OS-level machine identifier
Stable hardware identifiers where available
Computer name as display data only
```

Only hashed values should be sent:

```text
device_fingerprint_hash
installation_id_hash
```

---

## 13.8 Application API Client

Create:

```text
licensing_client.py
```

Responsibilities:

| Method | Purpose |
|---|---|
| `activate(email, licence_key)` | Calls `/activate` |
| `validate()` | Calls `/validate` |
| `deactivate_current_machine()` | Calls `/deactivate` |
| `parse_error_response()` | Maps server errors |
| `is_server_reachable()` | Optional helper |

Configuration values:

```text
LICENSING_API_BASE_URL=https://yourdomain.co.uk/wp-json/ckp-licensing/v1
PRODUCT_CODE=chromakey_pro
APP_VERSION=read from existing version file
```

---

## 13.9 Feature Gates

For beta, use licence features to control key areas.

Example:

```json
"features": {
  "export_enabled": true,
  "watermark": false,
  "batch_processing": true
}
```

App behaviour:

| Feature | App Use |
|---|---|
| `export_enabled` | Allows JPG export |
| `watermark` | Adds/removes beta watermark |
| `batch_processing` | Allows hot-folder/batch flow |
| `max_layers` | Optional future limit |
| `project_save_enabled` | Optional future control |

Recommended enforcement point:

```text
Block export if licence is invalid.
```

---

## 14. Python App Implementation Phases

### Phase APP-1: Licensing Configuration

Deliverables:

- Licensing config constants
- Product code
- API base URL
- App version read from existing version file
- Public signing key included in app package

Acceptance criteria:

- App knows its product code.
- App knows licensing API URL.
- App can read current version.
- Public key is available to verifier.

---

### Phase APP-2: Local Licence Cache

Deliverables:

- `license_cache.py`
- Read/write cache
- Clear cache
- Cache path creation
- JSON model validation

Acceptance criteria:

- Cache can be saved.
- Cache can be loaded.
- Missing cache returns `NO_LICENSE`.
- Corrupt cache is rejected.
- Invalid JSON does not crash app.

---

### Phase APP-3: Signature Verification

Deliverables:

- `signature_verifier.py`
- Verify signed payload from server
- Reject tampered cache

Acceptance criteria:

- Valid server response verifies.
- Edited expiry date fails verification.
- Edited feature flags fail verification.
- Missing signature fails verification.

---

### Phase APP-4: Device Identity

Deliverables:

- `device_identity.py`
- Generate installation ID
- Generate device fingerprint hash
- Return computer name
- Return OS name

Acceptance criteria:

- Installation ID persists between app restarts.
- Computer name is collected.
- Device fingerprint is stable on same machine.
- App sends only hashed identity values.

---

### Phase APP-5: Licensing API Client

Deliverables:

- `licensing_client.py`
- Activate call
- Validate call
- Deactivate call
- Error handling
- Timeout handling

Acceptance criteria:

- App can activate against test API.
- App handles server unavailable.
- App handles invalid licence.
- App handles activation limit reached.
- App handles expired/suspended/revoked responses.

---

### Phase APP-6: Activation UI

Deliverables:

- Activation dialog
- Email field
- Licence key field
- Validation/error messages
- Success flow into app

Acceptance criteria:

- App shows activation dialog when no valid licence exists.
- User can enter email/key.
- Successful activation stores cache.
- Failed activation displays useful message.
- App does not open licensed mode after failed activation.

---

### Phase APP-7: Startup Licence State Check

Deliverables:

- `license_state.py`
- Main app startup integration
- Cache checking
- Signature verification
- Expiry checking
- Validation due date checking
- Online validation where appropriate

Acceptance criteria:

- Valid licence allows app use.
- Expired licence blocks export/app usage.
- Invalid signature requires reactivation.
- Validation due triggers online check.
- Server success refreshes local cache.

---

### Phase APP-8: Grace Period Handling

Deliverables:

- Grace period state
- Warning UI
- Export restriction after grace expires

Acceptance criteria:

- Offline app works before validation due.
- Offline app works during grace with warning.
- Offline app blocks export after grace.
- Successful online validation clears warning.

---

### Phase APP-9: Activation Limit Handling

Deliverables:

- Activation limit dialog
- Display activated machines returned by server
- Clear message to user

Acceptance criteria:

- Limit reached response displays machine list.
- User understands they need an activation freed.
- Admin can deactivate from WordPress.
- User can retry activation successfully afterwards.

For beta, admin deactivation is enough initially.

---

### Phase APP-10: Current Machine Deactivation

Deliverables:

Menu option:

```text
Help → Licence → Deactivate this machine
```

Acceptance criteria:

- User can deactivate current machine.
- Server marks activation deactivated.
- Local cache is cleared.
- App returns to unlicensed state.
- Deactivated machine no longer counts against limit.

---

## 15. App UI Locations

Recommended menu structure:

```text
Help
    Licence Status
    Deactivate This Machine
```

### Licence Status Dialog

Should show:

```text
Licensed to: customer@example.com
Plan: Beta
Status: Active
Expires: 27 July 2026
Last validated: 27 April 2026
Next validation due: 27 May 2026
Activated machine: EVENT-LAPTOP
```

Buttons:

```text
Validate Now
Deactivate This Machine
Close
```

---

## 16. Error Handling Map

| App Condition | Message |
|---|---|
| No internet during valid period | No blocking message needed |
| No internet near due date | Licence validation will be required soon. |
| No internet after due date | Connect to the internet to validate your licence. |
| Grace active | You can continue until `[date]`. |
| Grace expired | Licence validation is overdue. Export is disabled. |
| Expired licence | Your licence expired on `[date]`. |
| Suspended licence | This licence has been suspended. |
| Revoked licence | This licence has been revoked. |
| Activation deactivated | This machine has been deactivated. |
| Device mismatch | This licence does not match this machine. |
| Invalid signature | Licence data is invalid. Please reactivate. |

---

## 17. Recommended Build Order

### 17.1 Build WordPress First

1. Plugin skeleton
2. Database tables
3. Admin create customer/licence
4. Generate beta key
5. Activate API
6. Validate API
7. Deactivate API
8. Admin activation list
9. Audit log
10. Rate limiting and hardening

### 17.2 Then Build App Integration

1. Add licensing modules
2. Add cache
3. Add signature verification
4. Add device identity
5. Add API client
6. Add activation dialog
7. Add startup validation
8. Add export gating
9. Add licence status screen
10. Add deactivate current machine

---

## 18. Beta Test Scenarios

### Scenario 1: Valid Activation

```gherkin
Given an active beta licence exists
When the user enters email and licence key
Then the machine is activated
And the app stores a signed licence cache
And export is enabled
```

---

### Scenario 2: Activation Limit Reached

```gherkin
Given a licence allows 2 activations
And 2 machines are already active
When a third machine attempts activation
Then activation is rejected
And the app shows the active machine list
```

---

### Scenario 3: Same Machine Reactivation

```gherkin
Given a machine is already activated
When the app is reinstalled on the same machine
Then the server reuses the existing activation where possible
And does not consume another activation slot
```

---

### Scenario 4: Expired Licence

```gherkin
Given a licence expiry date has passed
When the app validates
Then the server returns licence_expired
And the app disables export
```

---

### Scenario 5: Offline Before Validation Due

```gherkin
Given the app has a valid signed cache
And validation is not yet due
When the user opens the app offline
Then the app opens normally
```

---

### Scenario 6: Offline During Grace

```gherkin
Given validation is overdue
And the grace period has not expired
When the app opens offline
Then the app opens with a warning
```

---

### Scenario 7: Offline After Grace

```gherkin
Given validation is overdue
And grace has expired
When the app opens offline
Then export is disabled until validation succeeds
```

---

### Scenario 8: Admin Deactivates Machine

```gherkin
Given an admin deactivates an activation
When that machine next validates
Then the app enters activation_deactivated state
And requires reactivation
```

---

## 19. BA Delivery Backlog

### Epic 1: WordPress Licensing Plugin Foundation

Stories:

1. Create installable plugin shell.
2. Create plugin database tables.
3. Add plugin settings page.
4. Add admin capability checks.
5. Add plugin uninstall/deactivation behaviour.

---

### Epic 2: Admin Beta Licence Management

Stories:

1. Create customer.
2. Generate beta licence.
3. Set activation limit.
4. Set expiry date.
5. Suspend/revoke licence.
6. View licence details.
7. Record audit events.

---

### Epic 3: Desktop Activation API

Stories:

1. Activate licence by email/key.
2. Enforce activation limit.
3. Reuse activation for known machine.
4. Return signed licence grant.
5. Log activation attempts.

---

### Epic 4: Desktop Validation API

Stories:

1. Validate existing activation.
2. Update last validation date.
3. Return refreshed signed grant.
4. Reject expired/suspended/revoked licences.
5. Reject deactivated/revoked activations.

---

### Epic 5: Python App Licence Cache and Validation

Stories:

1. Store local signed licence cache.
2. Verify server signature.
3. Detect expired local licence.
4. Detect validation due date.
5. Attempt online validation.
6. Enforce grace period.
7. Disable export if licence invalid.

---

### Epic 6: Python App Activation UI

Stories:

1. Show activation dialog.
2. Submit email/key.
3. Display activation errors.
4. Store successful licence.
5. Display licence status.
6. Deactivate current machine.

---

## 20. Final Target Architecture

```text
[WordPress Plugin]
    ├── Admin creates beta customer
    ├── Admin generates licence key
    ├── Plugin stores hashed key
    ├── Plugin exposes REST API
    ├── Plugin tracks activations
    ├── Plugin signs licence grants
    └── Plugin logs validation/audit events

[Python App]
    ├── User enters email + licence key
    ├── App sends activation request
    ├── App receives signed licence grant
    ├── App stores local licence cache
    ├── App checks licence at startup
    ├── App validates with server when online
    ├── App requires validation every 30 days
    └── App disables export if licence becomes invalid
```

---

## 21. Summary

The beta licensing model should use the existing WordPress site as a temporary licensing backend through a custom plugin.

The plugin should provide:

- Admin-created beta licences
- Email + licence key activation
- Activation limits
- Expiry dates
- 30-day revalidation
- Optional grace period
- Machine tracking
- REST API endpoints
- Signed licence grants
- Audit logging

The Python app should provide:

- Activation UI
- Device identity generation
- API client
- Signed local licence cache
- Startup validation
- Grace period handling
- Export gating
- Licence status and deactivation UI

This provides a controlled beta licensing system without requiring a separate backend service during the early test period.
