# OmniSignal

Server-side offline conversion tracking — the application behind
[omnisignal.dev](https://omnisignal.dev).

This repository holds three things:

| Path | What it is |
| :-- | :-- |
| `app/`, `routes/`, `resources/` | The Laravel app: marketing site, licence portal, Lemon Squeezy checkout and webhooks |
| `packages/php-sdk/` | `electrictomcat/omnisignal-php-sdk` — a dependency-free PHP client for the five ad channels |
| `packages/wp-omnisignal/` | The WordPress / WooCommerce plugin |

The conversion engine itself is **not** here. It lives in
[`electrictomcat/laravel-google-ads-conversions`](https://github.com/electrictomcat/laravel-google-ads-conversions)
and is consumed as a Composer dependency. `app/OmniSignal/` contains only what
is specific to this application (the plugin builder, the test-event command and
the Stripe listener).

---

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
```

The conversion engine resolves from Packagist as
`electrictomcat/laravel-google-ads-conversions:^1.0`, so nothing else needs to
be on disk.

### Working on the engine alongside the app

To develop against a local checkout, add a path repository temporarily:

```bash
git clone git@github.com:electrictomcat/laravel-google-ads-conversions.git ../laravel-google-ads-conversions
composer config repositories.engine path ../laravel-google-ads-conversions
composer update electrictomcat/laravel-google-ads-conversions
```

Undo it with `composer config --unset repositories.engine` before committing —
a path repository does not resolve on a build server.

---

## Required configuration

Copy `.env.example` and fill it in. Two entries are not optional in production:

- **`LEMON_SQUEEZY_SIGNING_SECRET`** — webhooks are rejected outright when this
  is unset. Without it, anyone who can POST to `/webhooks/lemonsqueezy` could
  otherwise mint themselves a licence.
- **`SESSION_SECURE_COOKIE=true`** — the session cookie carries portal access.

Mail must also work: the licence portal proves ownership by emailing a signed
link, so a broken mailer means customers cannot reach their keys.

---

## The hosted Google Ads connector

Google Ads is the one channel a customer cannot configure inside their own site.
It needs an OAuth client secret — which cannot ship in a GPL WordPress plugin
whose source is public — and a developer token, which is issued against our
manager account rather than each customer's. So the authorisation happens here
and we upload on their behalf.

```
 Customer's site                    omnisignal.dev                  Google Ads
      │                                   │                              │
      │  1. activate(licence, domain)     │                              │
      │ ────────────────────────────────► │                              │
      │ ◄──── ingest_token (per domain) ──│                              │
      │                                   │                              │
      │                        2. customer authorises at /portal ──────► │
      │                                   │ ◄──── refresh token ─────────│
      │                                   │      (encrypted at rest)     │
      │  3. POST /api/v1/conversions      │                              │
      │ ────────────────────────────────► │  4. upload as the customer ► │
```

**The site never holds the licence key.** It authenticates with a token derived
from `HMAC(licence key + domain)`, so a compromised WordPress install cannot
read the key, reach the customer's other domains, or touch another customer.
Deactivating a domain revokes its token; rotating the licence key revokes all of
them. There is no second table of secrets to leak.

Ingest de-duplicates on `event_id` for seven days, so a retry from the site
never becomes a second conversion. A revoked grant marks the connection
`needs_reauth` and stops, rather than retrying three times an hour forever.

Requires `GOOGLE_ADS_OAUTH_CLIENT_ID`, `GOOGLE_ADS_OAUTH_CLIENT_SECRET` and
`GOOGLE_ADS_DEVELOPER_TOKEN`. Without them the connect button reports that
connections are unavailable rather than failing halfway through.

> Uploading on a customer's behalf makes us a data processor for their
> end-customers' hashed identifiers. That is a change in legal posture, not just
> architecture: it needs a DPA with each customer and a sub-processor
> disclosure. Only the channels listed above route through us — Meta, TikTok,
> Microsoft and LinkedIn still send directly from the customer's own server.

---

## Routes worth knowing

| Route | Access |
| :-- | :-- |
| `/` `/docs` `/terms` `/privacy` `/refunds` | Public |
| `/portal` | Public form; emails a signed link. Reveals nothing itself |
| `/portal/licences` | Signed link only, 30-minute expiry |
| `/thanks` | Post-checkout receipt page |
| `/api/v1/licenses/*` | Throttled per IP and per key |
| `/api/v1/conversions` | Per-domain bearer token; throttled |
| `/portal/connect/*` | Portal session from the emailed link |
| `/webhooks/lemonsqueezy` | HMAC-verified |
| `/ad-conversions` | Disabled unless `AD_CONVERSIONS_DASHBOARD_ENABLED=true`; HTTP Basic against the users table |

---

## Commands

```bash
php artisan ad-conversions:install    # publish config, report channel readiness
php artisan ad-conversions:test       # verify every channel against its live API
php artisan ad-conversions:upload     # flush the buffer and upload pending conversions
php artisan omnisignal:test-event     # send one live or test conversion
php artisan omnisignal:build-plugin   # package the WordPress plugin into public/downloads/
```

The scheduled work is `UploadPendingConversions` (hourly) and `model:prune` for
retention.

---

## Checks

```bash
php artisan test          # feature and unit suite
vendor/bin/phpstan        # static analysis
vendor/bin/pint --test    # formatting
npm run build             # frontend assets
```

All four run in CI on push and pull request.

---

## License

Proprietary. The `packages/php-sdk` directory is MIT; the WordPress plugin is
GPLv2 or later.
