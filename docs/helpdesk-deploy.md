# Deploying help.tijaraq.com

Single-tenant BeDesk install for TijaraQ merchant support. This app is separate from `app.tijaraq.com`.

## 1. Requirements

| Item | Value |
|---|---|
| PHP | 8.3 recommended (8.2 minimum). 8.5 works but prints vendor deprecations (hidden by `error_reporting` guards in `artisan` and `public/index.php`) |
| Extensions | `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `xml`, `curl`, `zip`, `intl`, `ftp`, `sodium`, `exif` |
| Database | MySQL 5.7+/8 or MariaDB 10.4+ (older MariaDB is supported: the index-rename migration was rewritten) |
| Web root | the `public/` directory (on shared hosting where that is impossible, keep the root `.htaccess` the installer creates) |
| Cron | one entry, see section 3 |
| Not needed | Redis, Horizon, Reverb/websockets, Meilisearch, a queue daemon |

`laravel/horizon` requires `ext-pcntl` and `ext-posix`, which Windows does not have. On Linux they exist and
`composer install` works normally. On Windows, set once and forget:

```powershell
[Environment]::SetEnvironmentVariable('COMPOSER_IGNORE_PLATFORM_REQS','ext-pcntl,ext-posix','User')
```

Do **not** commit those flags to `composer.json`.

## 2. Shared hosting deploy (no SSH)

1. Locally: `composer install --no-dev --optimize-autoloader` and `npm ci && npm run build`
   (if `tsc` fails on missing `@types/node`, run `npx vite build`; the type errors are pre-existing).
2. Upload the project including `vendor/` and `public/build/`. Do **not** upload `node_modules/`, `.git/`, `tests/`, `docs/`, `.env`.
3. Make these writable by the web user: `storage/`, `bootstrap/cache/`, `public/` (installer writes `.htaccess` and favicons), project root (installer writes `.env`).
4. Create an empty database and user. Open `https://help.tijaraq.com/install` and complete the wizard
   (requirements → database → admin account → finalize).
5. After finalize the installer sets `INSTALLED=true`, `APP_ENV=production`, `APP_DEBUG=false`.
   From then on `/install*` routes are not registered and return 404.
6. Seeded automatically on install: roles, permissions, ticket statuses, views, the TijaraQ departments
   (groups and ticket categories), the agent-only fields `tijaraq_company_id`, `tijaraq_plan`, `tijaraq_channel`,
   and the Bangla/Arabic locales (see section 6).

With SSH, the same flow works with `php artisan migrate --seed`-equivalent steps performed by the installer.
Do not run `migrate:fresh` on a live database.

## 3. Cron (the only scheduled process)

```
* * * * * cd /path/to/help.tijaraq && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs housekeeping, trigger processing, IMAP polling (if configured) and, when
`QUEUE_CONNECTION` is not `sync`, drains the queue with `queue:work --stop-when-empty --max-time=50` every minute.
The admin area shows a red alert if the scheduler has not run in 30 minutes.

## 4. `.env` keys

Set by the installer, review afterwards. Never commit `.env`.

| Key | Production value |
|---|---|
| `APP_NAME` | `"TijaraQ Help"` |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://help.tijaraq.com` |
| `INSTALLED` | `true` (locks the installer) |
| `DB_*` | database credentials |
| `QUEUE_CONNECTION` | `database` (drained by cron) or `sync` |
| `CACHE_STORE`, `SESSION_DRIVER` | `file` |
| `BROADCAST_CONNECTION`, `WEBSOCKETS_INTEGRATED` | `log`, `false` |
| `MAIL_*` | SMTP credentials; `MAIL_FROM_NAME="TijaraQ Support"`, `MAIL_FROM_ADDRESS=support@tijaraq.com` |
| `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE` | `true` and `lax` behind HTTPS |
| `SENTRY_DSN` | optional error tracking (already integrated). Larabug is not installed; add it with `composer require larabug/larabug` only if you want it |
| `MAILGUN_SECRET` | required if you use Mailgun inbound mail (signature check is mandatory) |

Secrets that must be unique per install (the template no longer ships any): `APP_KEY` (the installer generates it),
`REVERB_APP_KEY/SECRET` (only if Reverb is ever enabled).

## 5. After going live

- Confirm `/install` returns 404.
- `chmod 640 .env` and keep it outside any web-served path.
- Rotate the admin password created during install.
- **Gmail inbound mail:** the Pub/Sub push endpoint now requires a token. Print the URL with
  `php artisan helpdesk:gmail-webhook-url` and use that exact URL as the push endpoint.
- **Mailgun inbound mail:** configure the Mailgun signing key as `MAILGUN_SECRET`; unsigned requests are rejected.
- Uploads: attachments are stored on the private `local` disk and served through an authorized controller.
  Only images/PDF/text/CSV/ZIP/Office types are accepted for ticket attachments (`config/filesystems.php`).

## 6. Languages

English is the default. `bn` (Bangla) and `ar` (Arabic) exist as locales that start with English text; translate them in
Admin → Localization. **The React client has no RTL layout support**, so Arabic renders left-to-right until RTL styling is added.

## 7. Backups

Before every deploy or upgrade:

```bash
mysqldump --single-transaction -u USER -p DBNAME | gzip > backup-$(date +%F).sql.gz
tar czf storage-$(date +%F).tgz storage/app .env
```

Restore = import the SQL dump and extract `storage/app` and `.env`.

## 8. Tests (development only)

```
php vendor/phpunit/phpunit/phpunit
```

Tests use their own database `help_tijrak_test` (created once with `CREATE DATABASE help_tijrak_test`) and
refuse to run against any other database. They cover ticket and attachment authorization (customer A cannot reach customer B).
