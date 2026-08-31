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

## Routes worth knowing

| Route | Access |
| :-- | :-- |
| `/` `/docs` `/terms` `/privacy` `/refunds` | Public |
| `/portal` | Public form; emails a signed link. Reveals nothing itself |
| `/portal/licences` | Signed link only, 30-minute expiry |
| `/thanks` | Post-checkout receipt page |
| `/api/v1/licenses/*` | Throttled per IP and per key |
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
