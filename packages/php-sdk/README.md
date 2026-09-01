# OmniSignal Universal PHP SDK

**Pure Signal. Zero Noise. &bull; Server-Side Conversion Tracking for Any PHP Application**

Zero-dependency PHP client for sending server-side offline conversions to
**Google Ads**, **Meta CAPI**, **TikTok Events API**, **LinkedIn CAPI** and
**Microsoft Advertising**.

Every channel makes a real, authenticated HTTP request and reports what the
platform actually said. A channel that is not fully configured is not
registered, and a rejected upload is reported as a failure — never as a
success with nothing sent.

---

## Installation

Once published to Packagist:

```bash
composer require electrictomcat/omnisignal-php-sdk
```

Until then, point Composer at the repository:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/electrictomcat/omnisignal" }
    ],
    "require": { "electrictomcat/omnisignal-php-sdk": "dev-main" }
}
```

Requires PHP 8.1+, `ext-json` and `ext-curl`. No other dependencies.

---

## Quickstart

```php
use OmniSignal\OmniSignalClient;

$client = OmniSignalClient::create([
    'meta' => [
        'pixel_id' => '1234567890123456',
        'access_token' => 'EAAG...',
    ],
    'tiktok' => [
        'pixel_code' => 'C123456789',
        'access_token' => '...',
    ],
]);

$result = $client->record(
    eventName: 'Purchase',
    value: 149.00,
    currency: 'USD',
    orderId: 'ORD-8921',
    user: [
        'email' => 'customer@example.com',   // normalized + SHA-256 hashed
        'phone' => '+15551234567',
        'client_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ],
    clickIds: [
        'fbclid' => $_COOKIE['omni_fbclid'] ?? $_GET['fbclid'] ?? null,
        'ttclid' => $_COOKIE['omni_ttclid'] ?? $_GET['ttclid'] ?? null,
    ],
);

// => ['meta' => ['success' => true, 'count' => 1, 'errors' => [], ...], ...]
```

`$result` is keyed by channel. Check `success` and read `errors` — a failure is
never silent.

---

## Channel configuration

Each channel is registered only when everything it needs is present. Call
`activeChannels()` to see what will actually be sent to.

| Channel | Required keys |
| :-- | :-- |
| `meta` | `pixel_id`, `access_token` |
| `tiktok` | `pixel_code`, `access_token` |
| `google` | `developer_token`, `client_id`, `client_secret`, `refresh_token`, `customer_id`, `conversion_action` |
| `linkedin` | `access_token`, `conversion_rule_id` |
| `microsoft` | `developer_token`, `customer_id`, `account_id`, `access_token` |

Notes:

- **Google** `conversion_action` is either the full resource name
  (`customers/1234567890/conversionActions/555`) or just the numeric ID.
  Optional: `login_customer_id` for manager accounts, `api_version`.
- **Microsoft** `customer_id` (the manager) and `account_id` (the ad account)
  are different values; `ApplyOfflineConversions` requires both.
- **LinkedIn** retires an API version about a year after release, at which
  point calls return 426. Set `version` to a current `YYYYMM` string when that
  happens; the SDK reports the expiry explicitly rather than failing opaquely.

### Phone numbers

Set `default_calling_code` on any channel (e.g. `'1'` for the US, `'44'` for
the UK) if you store national-format numbers. Without it, a number carrying no
country code is **dropped rather than guessed at** — a hash built on the wrong
country matches nobody and is indistinguishable from a working upload.

---

## Verifying your credentials

```php
foreach ($client->testConnections() as $channel => $result) {
    echo $channel, ': ', $result['success'] ? 'OK' : $result['message'], PHP_EOL;
}
```

Each check makes a real authenticated call. It will tell you about a revoked
token, a wrong account ID, or an expired API version.

---

## License activation

```php
$res = $client->activateLicense('OMNI-XXXX-XXXX-XXXX-XXXX', 'example.com');

if ($res['status'] === 200) {
    echo "License activated!";
}
```

---

## License

MIT.
