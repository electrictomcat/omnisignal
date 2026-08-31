# OmniSignal Universal PHP SDK

**Pure Signal. Zero Noise. &bull; Attribution Nirvana for Any PHP Application**

Zero-dependency, high-performance PHP client for broadcasting server-side offline conversions directly to **Google Ads**, **Meta CAPI**, **TikTok Events API**, **LinkedIn CAPI**, and **Microsoft Advertising**.

---

## ⚡ Installation

```bash
composer require omnisignal/php-sdk
```

---

## 🚀 Quickstart

```php
use OmniSignal\OmniSignalClient;

$client = OmniSignalClient::create([
    'meta' => [
        'pixel_id' => '1234567890123456',
        'access_token' => 'EAAG...your_token',
    ],
    'tiktok' => [
        'pixel_code' => 'C123456789',
        'access_token' => 'your_tiktok_token',
    ],
]);

// Broadcast a conversion event across all configured ad platforms
$result = $client->record(
    eventName: 'Purchase',
    value: 149.00,
    currency: 'USD',
    orderId: 'ORD-8921',
    user: [
        'email' => 'customer@example.com', // Auto SHA-256 hashed
        'phone' => '+15551234567',
    ],
    clickIds: [
        'fbclid' => $_COOKIE['omni_fbclid'] ?? $_GET['fbclid'] ?? null,
        'ttclid' => $_COOKIE['omni_ttclid'] ?? $_GET['ttclid'] ?? null,
    ]
);
```

---

## 🔑 License Activation

```php
$res = $client->activateLicense('OMNI-XXXX-XXXX-XXXX', 'example.com');
if ($res['status'] === 200) {
    echo "License activated!";
}
```
