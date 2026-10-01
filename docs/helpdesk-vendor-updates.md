# Merging future BeDesk (CodeCanyon) releases

The vendor's self-updater and license check were removed (they downloaded and extracted code over the app from
`support.vebto.com`). Updates are now applied by hand through git, so you can review every change.

## Branches

| Branch | Purpose |
|---|---|
| `vendor/original` | Pristine contents of the CodeCanyon zip, **nothing else**. One commit per vendor release. |
| `chore/helpdesk-audit-cleanup` → `main` | Our version: vendor code plus the cleanup commits. |

> **Gap to close once:** the first import commit (`cb2b857`) was taken from a working copy that had already been edited
> locally (see its commit message), so it is not byte-identical to the vendor zip. Create `vendor/original` from the
> original zip you downloaded:
>
> ```bash
> git checkout --orphan vendor/original && git rm -rf . && unzip ../bedesk-3.0.8.zip -d . && git add -A
> git commit -m "vendor: BeDesk 3.0.8 (pristine)"
> ```
>
> Use the same ignore rules (`.gitignore`, `.git/info/exclude`) so `vendor/`, `node_modules/`, `.env` are not tracked.
> `common/` is the vendor's git submodule; it is committed here as plain files.

## Update procedure

1. Back up the database and `storage/app` (see `helpdesk-deploy.md`).
2. Commit the new release on `vendor/original`:
   ```bash
   git checkout vendor/original
   rm -rf app common config database modules public resources routes tests   # keep .git
   unzip ../bedesk-NEW.zip -d .
   git add -A && git commit -m "vendor: BeDesk NEW"
   ```
3. See what changed upstream and which of our changes it touches:
   ```bash
   git diff vendor/original~1 vendor/original --stat
   git diff vendor/original~1 vendor/original -- app common routes   # review
   ```
4. Merge into our branch and resolve conflicts:
   ```bash
   git checkout main && git merge vendor/original
   ```
   Expect conflicts in files we changed. Our footprint, to re-apply or keep:
   - Removed: `Core/Install/{LicenseController,UpdateController,Updater/*}`, update/license routes, `modules/envato`,
     `app/Demo`, Envato code paths in the React client (search for `envato`).
   - Hardened: `SecurityHeaders` middleware, Gmail/Mailgun webhooks, `StoreFile`, `config/filesystems.php`,
     `ConversationFileEntryPolicy`, `MakeWebRequestAction`, `env.example`.
   - Added: `TijaraqDefaultsSeeder`, `routes/console.php` queue + `helpdesk:gmail-webhook-url`, `tests/`, `docs/`.
   If the vendor re-adds the license check or updater, drop their version of those files.
5. Dependencies and assets:
   ```bash
   composer install            # or: composer update <only the packages the release requires>
   npm ci && npm run build     # or: npx vite build
   php artisan migrate --force
   php artisan config:clear && php artisan route:clear && php artisan view:clear
   ```
6. Re-run the verification checklist:
   - `composer audit` and `npm audit --omit=dev` have no critical/high findings.
   - No outbound calls to vendor domains:
     `rg "support\.vebto|api\.envato|register-purchase-code|get-download-url" --glob '*.php' --glob '*.ts*' -g '!vendor' -g '!node_modules'`
   - No obfuscation: `rg "\b(eval|shell_exec|passthru|proc_open|popen|gzinflate|str_rot13)\s*\(" app common routes config database`
   - `php vendor/phpunit/phpunit/phpunit` passes.
   - `php artisan route:list` shows no `update`, `license`, `envato` or demo routes.
   - Smoke test: admin login, create customer, open ticket with attachment, agent reply, status change, KB search,
     customer B cannot open customer A's ticket, `/install` returns 404.
7. Tag and deploy: `git tag helpdesk-NEW` then follow `helpdesk-deploy.md`.

## Notes

- The vendor's migrations are idempotent where they were written defensively, but always run them on a copy first.
- The MariaDB-compatibility fix lives in `database/migrations/2025_04_17_113955_rename_assigned_to_to_assignee_id.php`
  (`RENAME INDEX` needs MariaDB ≥ 10.5.2). A merge may overwrite it; re-apply if you still run an older MariaDB.
- Keep `config('app.version')` / `APP_VERSION` in `env.example` in sync with the release you merged.
