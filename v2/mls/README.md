# MLS Integration Guide

This folder contains a modular MLS connector for Danny Homes. It supports both the modern **RESO Web API** (recommended) and the legacy **RETS** protocol.

---

## Quick-start checklist

1. Contact your MLS board's IDX/data department and request API access.
2. They will give you credentials — follow the relevant section below.
3. Fill in the `config.php` constants (see each section).
4. Set `MLS_ENABLED = true` in `config.php`.
5. Run a test sync: `php mls/sync.php` from the project root.
6. Set up a cron job to auto-sync.

---

## Option A — RESO Web API (recommended, modern)

Supported by: **MLSGrid**, **Spark API**, **Bridge Interactive**, **Trestle**, and most boards after 2019.

### Credentials you need from your board
| Setting | Example | Where to get it |
|---|---|---|
| Token endpoint | `https://api.mlsgrid.com/oauth2/token` | Your board's API docs |
| API endpoint | `https://api.mlsgrid.com/v2` | Your board's API docs |
| Client ID | `abc123` | Board/provider dashboard |
| Client Secret | `xyz987` | Board/provider dashboard |

### config.php settings
```php
define('MLS_ENABLED',       true);
define('MLS_PROVIDER',      'reso');
define('MLS_TOKEN_URL',     'https://api.mlsgrid.com/oauth2/token');
define('MLS_ENDPOINT',      'https://api.mlsgrid.com/v2');
define('MLS_CLIENT_ID',     'YOUR_CLIENT_ID');
define('MLS_CLIENT_SECRET', 'YOUR_CLIENT_SECRET');
```

### Customising field mappings
Open `mls/ResoAdapter.php` and edit the `normalise()` method.
The field names follow [RESO Data Dictionary 1.7](https://www.reso.org/reso-web-api/).
If your board uses non-standard names, map them in `normalise()`.

---

## Option B — RETS (legacy, still common)

### Step 1: Install phRETS

```bash
composer require troytoney/phrets
```

Or download manually: https://github.com/troytoney/phRETS

### Credentials you need from your board
| Setting | Example |
|---|---|
| RETS Login URL | `http://rets.yourmls.com/login` |
| Username | `agent_username` |
| Password | `agent_password` |
| Resource name | `Property` |
| Class name | `Residential` |

### config.php settings
```php
define('MLS_ENABLED',   true);
define('MLS_PROVIDER',  'rets');
define('MLS_ENDPOINT',  'http://rets.yourmls.com/login');
define('MLS_CLIENT_ID', 'YOUR_USERNAME');
define('MLS_API_KEY',   'YOUR_PASSWORD');
```

### Discovering field names
RETS field names vary per board. To print the metadata for your board:
```php
require_once 'vendor/phrets/phrets.php';
$rets = new phRETS();
$rets->Connect($loginUrl, $user, $pass);
$meta = $rets->GetMetadata('METADATA-TABLE', 'Property', 'Residential');
print_r($meta);
```
Then update the field name mappings in `mls/RetsAdapter.php → normalise()`.

---

## Running the sync

### Manual (one-time test)
```bash
# From the project root:
php mls/sync.php

# Force a re-sync (ignore the interval timer):
php mls/sync.php  # or open /mls/sync.php?force=1 in browser while logged in as admin
```

### Automatic (cron — recommended)
Add to your hosting control panel's cron scheduler:
```
# Every hour at minute 5:
5 * * * * php /home/youruser/public_html/danny-zillow-site/mls/sync.php >> /tmp/mls-sync.log 2>&1
```

Adjust the path to match your hosting setup. Most cPanel hosts have a "Cron Jobs" tool.

---

## File structure

| File | Purpose |
|---|---|
| `MlsConnector.php` | Abstract base class — defines the interface all adapters must implement |
| `ResoAdapter.php` | RESO Web API implementation (OAuth 2 + OData/JSON) |
| `RetsAdapter.php` | RETS implementation (requires phRETS library) |
| `sync.php` | Cron-ready sync runner — fetches from MLS, upserts to DB |

---

## Sync log

Every sync run is logged to the `mls_sync_log` table:
```sql
SELECT * FROM mls_sync_log ORDER BY started_at DESC LIMIT 20;
```

---

## How listings are matched

- MLS listings are matched on `mls_id` (the MLS listing number).
- If a listing is no longer in the active feed, its `status` is set to `off-market`.
- Manual listings (`source = 'manual'`) are never touched by the MLS sync.

---

## IDX compliance note

Using MLS data on your website requires an **IDX (Internet Data Exchange) agreement** with your MLS board. Make sure you have signed and are complying with their IDX display rules before enabling this integration.
