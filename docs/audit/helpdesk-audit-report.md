# Helpdesk audit report: help.tijaraq.com

> Historical snapshot from 2026-10-01. The current application has since been rebranded as TijaraQ Help and its former purchase-code UI has been removed. Keep this report as a record of the original source and earlier findings.

Stage 1 (read-only audit). Nothing was deleted or refactored. Date: 2026-10-01. Branch: `chore/helpdesk-audit-cleanup`.

Labels: `UNVERIFIED` = reasoned from code but not exercised at runtime. Line numbers refer to the files as committed in `cb2b857`.

> **Baseline caveat.** The directory was not a git repo and was not the pristine vendor zip. Before this audit, an earlier session had already changed three files (listed in the baseline commit message): the rename-index migration, `artisan`, and `public/index.php`. `.env` was also edited (INSTALLED, cookies, Telescope, APP_URL). To get a true vendor baseline, diff against the original CodeCanyon zip (see open question 1).

---

## 1. Executive summary

**Verdict: GO WITH FIXES.**

The product is **BeDesk** (v3.0.8, by Vebto / Ramunas), a Laravel 12 + React helpdesk. The source is readable (no ionCube, no obfuscation, no backdoor patterns in the code the author wrote). Ticket authorization is sound. The licensing mechanism is a single, removable phone-home to `support.vebto.com`, with no kill switch.

**Top 5 risks**

1. **Vulnerable dependencies.** `composer audit` reports 58 advisories (2 critical, 18 high). Most are fixed by a targeted `composer update` within the existing constraints. `npm audit` reports 15 high.
2. **Unauthenticated Gmail webhook** (`/tickets/mail/incoming/gmail`): anyone can POST to it and rewrite the stored `lastHistoryId`. Mailgun signature checking is optional.
3. **Default secrets shipped in `env.example`**, copied verbatim into `.env`: `WIDGET_HMAC_SECRET`, Reverb app key/secret. Every buyer shares them.
4. **No security headers anywhere**, and no rate limiting on guest ticket creation (captcha is optional).
5. **Vendor phone-home and remote self-updater** (downloads and extracts a zip over the app, including `vendor/`). Fine to remove, but it is a remote code-update path and should be disabled for a locked-down deployment.

**Not a blocker:** no encoded files, no kill switches, no hidden admin accounts, no domain lock.

---

## 2. Inventory

| Item | Finding |
|---|---|
| Product / version | BeDesk 3.0.8 (`.env` `APP_VERSION`) |
| Framework | Laravel 12.39.0 (`^12.16`), PHP `>=8.2` (running on 8.5.10 locally) |
| Frontend | React + TypeScript SPA (Vite), Tailwind. Built assets in `public/build` |
| DB | MySQL/MariaDB (local: MariaDB 10.4.32, which needed a migration fix) |
| Search | Scout (`mysql` driver by default; Meilisearch, Algolia, Elasticsearch, TNTSearch supported) |
| Realtime / queues | Reverb (needs a daemon), Horizon (needs Redis + pcntl), default `QUEUE_CONNECTION=sync` |
| Structure | `app/` (helpdesk domain), `common/foundation` (shared framework, a git submodule `RamunasO/common-new` that is a plain directory here), `modules/{ai,livechat,envato}`, `routes/` (395 routes), 84 + 147 + 4 migrations |
| Tests | `tests/` contains only the two default `ExampleTest` files |

Size by directory (files / MB): app 230 / 0.5, common 3991 / 5.7, modules 335 / 0.9, public 581 / 18.0, resources 440 / 1.5, vendor 20239 / 153.5, node_modules 45323 / 485.6.

**Domain modules in `app/`:** Attributes (custom fields), CannedReplies, Contacts, Conversations (tickets + email), HelpCenter (KB), Reports, Team (agents/groups), Triggers (automation), Webhooks, Demo.

**Foundation (`common/foundation/src`) includes:** Billing (Stripe/PayPal), Workspaces, Websockets, Domains (custom domains), Localizations, Pages, Comments, Votes, Admin, Logging, Search, Files, Notifications, Settings, Auth.

---

## 3. License & phone-home findings

All outbound vendor traffic goes to `support.vebto.com`. There are **three** call sites.

| File:line | Endpoint | Data sent | Trigger | On failure | Recommendation |
|---|---|---|---|---|---|
| `common/foundation/src/Core/Install/LicenseController.php:33-40` | `POST https://support.vebto.com/envato/register-purchase-code` (with `verify=false`, i.e. TLS verification disabled) | purchase code, Envato item ID, app domain | Admin submits a code in Settings > System > License | Returns an error to the admin UI; app keeps working | Replace with a local no-op that writes `ENVATO_PURCHASE_CODE` to `.env` |
| `common/foundation/src/Core/Install/Commands/CheckIfUpdateAvailableCommand.php:16-21` | `GET https://support.vebto.com/envato/updates/get-latest-version` | purchase code | Scheduler daily 03:20, only if a purchase code is set (`CommonServiceProvider.php:603`) | Clears cached version; nothing else | Remove command and schedule entry |
| `common/foundation/src/Core/Install/Updater/Steps/DownloadUpdateStep.php:20-28` | `POST https://support.vebto.com/envato/updates/get-download-url`, then downloads the returned zip | purchase code | Admin clicks Update, or `UpdateAppCommand`. `UpdateApp.php:41` refuses to run without a code | Update aborts | Remove the whole updater (see below) |

**Self-updater (remote code replacement).** `UpdateApp.php:14-26` deletes `app, bootstrap, common, config, database, public/build, public/vendor, resources, routes, tests, vendor` (and `modules`) and replaces them with the downloaded zip. `InstallOrUpdateModule.php` does the same for the AI and LiveChat modules. This is the vendor's intended update path, not a backdoor, but it executes remotely supplied code with the app's filesystem rights.

**Admin UI nagging.** `Admin/SiteAlertsController.php:39-52` shows a "License is not activated" error alert when no purchase code is set. This is cosmetic only. Nothing is disabled.

**Other external calls (all admin-configured or optional, not vendor-controlled):**

| File | Endpoint | Purpose |
|---|---|---|
| `modules/envato/src/EnvatoApiClient.php`, `SocialiteProviders/EnvatoProvider.php` | `api.envato.com` | Verify *the buyers of your own* Envato products. Not applicable to TijaraQ. |
| `config/prism.php` | OpenAI, Mistral, Groq, xAI, Gemini, DeepSeek, ElevenLabs, Voyage, OpenRouter | AI module. Uses keys the admin enters (no vendor keys found). |
| `Domains/Validation/ValidateLinkWith{GoogleSafeBrowsing,Phishtank}.php` | Google, Phishtank | URL checks. Part of the custom-domain feature. |
| `Validation/CaptchaTokenValid.php`, `Settings/Validators/CaptchaCredentialsValidator.php` | Google reCAPTCHA, Cloudflare Turnstile | Optional captcha |
| `Billing/Gateways/*` | Stripe, PayPal | Payments (REMOVE) |
| `Websockets/API/*` | Pusher, Ably | Realtime, only if configured |
| `framework.blade.php:110-122` | `googletagmanager.com` | Only if `analytics.tracking_code` is set. Value is Blade-escaped. |
| `framework.blade.php:72`, `errors/503.blade.php:6` | `fonts.googleapis.com` | Web fonts |
| `Demo/CreateDemoToolsAndFlows.php:28` | `jsonplaceholder.typicode.com` | Demo data only |
| `framework.blade.php:3` | Sentry | Only if `SENTRY_DSN` is set |

**Not found:**

- No domain locking. `Core/AppUrl.php` reads the request host only to *auto-set* `APP_URL` before install.
- No kill switch. No code disables the app, deletes files, or wipes the DB based on license state.
- The only destructive demo code, `Demo/ResetDemoSite.php:70` (`Schema::dropAllTables()`), runs only when `IS_DEMO_SITE=true`, and is scheduled at `routes/console.php:10` (guarded by the same flag, UNVERIFIED that the guard wraps the schedule line). **Make sure `IS_DEMO_SITE` is never set in production**; Stage 2 should delete this code.
- No purchase code is present in `.env` (never activated), so there is nothing to mask or move.

**Modules licensed separately.** `config/modules.php` shows `ai` (item 59719065) and `livechat` (item 59719106) are *separate paid CodeCanyon items* with their own purchase codes. The code is present on disk. See open question 2.

---

## 4. Security findings

| ID | Severity | File:line | Issue | Evidence | Fix |
|---|---|---|---|---|---|
| S-01 | High | `composer.lock` (see section 5) | 58 known advisories in installed packages | `composer audit`: 2 critical, 18 high, 29 medium, 6 low, 3 unrated | Targeted `composer update` (section 5) |
| S-02 | High | `app/Webhooks/Controllers/GmailWebhookController.php:14-34`, route `routes/web.php:59-62` | Gmail push webhook has **no authentication and no Pub/Sub JWT verification**. Any visitor can POST `{"message":{"data":"<b64 {historyId}>"}}`; the code overwrites `lastHistoryId` in the token file (`file_put_contents`), causing missed or re-fetched mail. A malformed body raises a TypeError (500). | `$newHistoryId = json_decode(base64_decode(request()->input('message.data')), true)['historyId'];` | Verify the Google OIDC bearer token (or a shared secret in the push URL); validate input |
| S-03 | High | `env.example` (`WIDGET_HMAC_SECRET`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`) | **Static secrets shipped in the vendor `.env` template.** Local `.env` matches the template, so every installation using the template shares them. `WIDGET_HMAC_SECRET` signs the LiveChat visitor identity (`AppBootstrapData.php:47-51`), so a known secret lets anyone forge a verified identity. Mapping to `settings('app.widget_hmac_secret')` is UNVERIFIED. | `.env` value == `env.example` value (compared without printing) | Generate fresh random values at install; ship placeholders only |
| S-04 | Medium | `app/Webhooks/Controllers/MailgunWebhookController.php:63`, `EmailApiWebhookController.php` | Mailgun signature verification is **optional** (`incoming_email.mailgun.verify`). When off, anyone can POST and create tickets or inject replies as any sender. The check also uses `===` (not `hash_equals`). Default value UNVERIFIED. | `if (!settings('incoming_email.mailgun.verify')) { return true; }` | Force verification on; use `hash_equals` |
| S-05 | Medium | `common/foundation/src/Core/Install/UpdateController.php:14-20`, routes `common/foundation/routes/web.php:25-26` | `/update` and `/update/perform` skip the admin middleware whenever `env.example`'s `app_version` differs from `config('app.version')` (the post-upload window), or when `disable_update_auth` is set in `.env`. `runManualUpdateActions()` (migrations/seed actions) then runs unauthenticated. | `if (!$disableUpdateAuth && version_compare(config('app.version'), $this->getAppVersion()) === 0) { $this->middleware('isAdmin'); }` | Delete the updater and these routes in Stage 2 |
| S-06 | Medium | Whole app (only `Files/Tus/TusServer.php:362` sets `X-Content-Type-Options`) | **No security headers**: CSP, HSTS, X-Frame-Options, Referrer-Policy | `rg` for those header names returns one hit | Add a middleware (Stage 2.2) |
| S-07 | Medium | `app/Conversations/Customer/Controllers/CustomerTicketsController.php:126`, `common/foundation/routes/api.php:138-139` | Guest ticket creation (`tickets.guest_tickets`) has only optional captcha and **no throttle**. Throttle exists on login (Fortify limiter) and email verification only. Forgot-password throttling: UNVERIFIED. | `'captcha_token' => [new CaptchaTokenValid('new_ticket')]` | Add `throttle` to ticket-create, reset-password, and the mail webhooks |
| S-08 | Medium | `common/foundation/src/Files/Actions/StoreFile.php:111-125` | The public-disk guard only blocks `php*`/`phtml` and `application/x-php`. Not blocked: `.phar`, `.html`, `.svg`. The public upload types (`brandingImages`, `conversationImages`) accept `image`, which may include SVG: stored XSS if served inline from the app origin. UNVERIFIED which extensions `image` expands to. Private attachments are stored on a non-public disk (good). | `startsWith(['php', 'phtml'])` | Block `svg/html/phar` on public types, or sanitize SVG |
| S-09 | Medium | `config/filesystems.php:21-26` | `conversationAttachments` (private) has **no `accept` list**, so any file type is allowed up to 25 MB. Mitigated by the private disk and the authorized download controller. | upload type has only `max_file_size` | Add an allow-list (pdf, images, office, txt, zip) |
| S-10 | Low | `app/Conversations/Policies/ConversationFileEntryPolicy.php:40-52` | Attachment access check looks up `ConversationItem` by `model_id` **without checking `model_type`**. A file linked to a different model type whose id collides with a customer's conversation-item id could grant access (cross-type id confusion). Exploitability UNVERIFIED. | `ConversationItem::query()->where('id', $value->model_id)->value('conversation_id')` | Constrain on `model_type` |
| S-11 | Low | `common/foundation/src/Files/Traits/HashesId.php:10` | File "hashes" are `base64(id\|padding)`, so they are enumerable. Authorization is still enforced (`DownloadFileController` -> `authorize('download')`), so this is information only. | `base64_encode(str_pad($id.'\|', 10, 'padding'))` | Optional: use signed/opaque ids |
| S-12 | Low | `app/Triggers/Actions/MakeWebRequestAction.php:24` | Trigger "make web request" POSTs to an admin/agent-supplied URL with no private-IP/SSRF filter. Needs `triggers` permission. | `Http::throw()->post($url, $payload)` | Block private/link-local ranges or remove the action |
| S-13 | Low | `config/reverb.php:83` | `allowed_origins => ['*']` | n/a | Restrict to the app origin, or drop Reverb on shared hosting |
| S-14 | Low | `CustomerTicketsController.php:21,55` | `$this->middleware('auth:sanctum')` is called inside actions, where it has no effect. Guests get an empty list or 404 instead of 401. No data exposure. | n/a | Cosmetic; move to constructor |
| S-15 | Info | `env.example` | Template defaults are dev-style: `APP_DEBUG=true`, `APP_ENV=local`, `TRUST_ALL_PROXIES=true`, `SESSION_SAME_SITE=none`. The installer flips env/debug (`InstallController.php:156-169`), but a manually deployed `.env` will not. `ConfigureCookies` middleware downgrades secure cookies on plain HTTP. | n/a | Ship production defaults |

**Checked and found OK**

- **Obfuscation / backdoors.** In the author's code (`app`, `common`, `modules`, `routes`, `config`, `database`, `public`): no `eval`, `assert`, `create_function`, `shell_exec`, `exec`, `system`, `passthru`, `proc_open`, `popen`, `gzinflate`, `str_rot13`, or `/e` regex. `base64_decode` hits are legitimate (Gmail/mail attachments, Tus, filter parsing). No ionCube / SourceGuardian / Zend Guard headers.
- **`public/`**: no stray `.php` other than `index.php`; no adminer/phpinfo/test files. Contains `swagger.yaml`, `demo-files/`, `vendor/{horizon,telescope}` assets.
- **Hidden access.** No hardcoded admin credentials, master passwords, or magic-login routes. `ImpersonateUserController` requires `isAdmin`.
- **IDOR on tickets.** Customer routes scope by owner: `CustomerTicketsController@show` uses `where('user_id', Auth::id())`. Messages use `authorize('show'|'reply')` against `ConversationPolicy`, which requires `tickets.update` permission or `user_id === $user->id`. Internal notes are filtered for non-agents (`PaginateConversationItems.php:19-27`). Agent routes all call `authorize(...)`. Runtime IDOR tests are a Stage 2 deliverable.
- **Installer lockdown.** `common/foundation/routes/web.php:109` registers `/install*` only when `!config('app.installed')`. After install they return 404. `InstallController` also refuses a non-empty database.
- **SQL injection.** The raw-SQL scan was inconclusive (my filter command errored). `CustomerTicketsController.php:34` uses `orderByRaw` with an enum constant (safe). A full review is pending (see section 10).
- **Rich text.** Messages go through `MessageBodyPurifier`; HTMLPurifier and htmLawed are dependencies. Several `dangerouslySetInnerHTML` sites exist in the client (listed in Stage 2 scope), not audited individually: UNVERIFIED.
- **Auth.** Fortify, with a login rate limiter, 2FA, email verification (`verified` middleware), and Sanctum tokens.

---

## 5. Dependency findings

**Composer (`composer audit`, 58 advisories):** 2 critical, 18 high, 29 medium, 6 low, 3 unrated. No abandoned packages reported.

| Severity | Package | Note |
|---|---|---|
| Critical | `laravel/reverb` <1.7.0 (CVE-2026-23524) | Insecure deserialization, only with Redis horizontal scaling. Not applicable if Reverb is dropped or run without Redis. |
| Critical | `mtdowling/jmespath.php` <2.9.1 (CVE-2026-54133) | Transitive via AWS SDK |
| High | `laravel/framework` <12.60.0 | CRLF injection in the `email` rule. 12.39.0 is installed. |
| High | `guzzlehttp/guzzle` <7.15.2, `symfony/mime`, `zbateson/mail-mime-parser`, `phpseclib` | Header injection / host bypass / padding oracle |
| High | `league/commonmark` (9 advisories) | DoS and an XSS filter bypass |
| High | `aws/aws-sdk-php`, `google/protobuf` | |
| Medium | `livewire/livewire` | DOM XSS. Livewire is not used by the app (UNVERIFIED which package pulls it in). |

Most of these are patch/minor releases inside the existing constraints (`laravel/framework ^12.16` allows >=12.69). Plan: `composer update laravel/framework symfony/* guzzlehttp/* league/commonmark zbateson/* phpseclib/* aws/aws-sdk-php ... --with-dependencies`, then re-run `composer audit`.

**npm (`npm audit --omit=dev`): 15 high, 3 moderate, 1 low, 0 critical.**
Direct: `axios`, `nanoid`, `postcss`, `react-router`, `url-regex`, `vite`. Transitive: `@tiptap/core`, `ws`, `rollup`, `minimatch`, `socket.io-parser`, others. `vite`, `postcss`, and `rollup` are build-time only.

**Compatibility.** Laravel 12 is supported. PHP 8.5 is not officially supported by several locked packages: `cocur/slugify` was too old (updated), and vendor code emits PHP 8.5 deprecations. Target **PHP 8.3** for production.

**Bundled libraries in `public/`.** None found: no old jQuery / Bootstrap / CKEditor / TinyMCE.

**Integrity of `vendor/`.** `composer install` restored packages from `composer.lock` (dist hashes), so no tampering was detected. A byte-level comparison against source is UNVERIFIED. `composer.json` sets `minimum-stability: dev` (with `prefer-stable`), which is risky for future updates.

---

## 6. Install / runtime requirements

- **PHP:** >=8.2 (use 8.3). Extensions: pdo/pdo_mysql, xml, mbstring, fileinfo, openssl, gd, curl, zip, intl, plus `ftp` (flysystem-ftp). `pcntl` and `posix` are required only by Horizon (not available on Windows).
- **Cron:** one entry for `php artisan schedule:run` every minute. `SiteAlertsController` warns if the scheduler has not run in 30 minutes.
- **Queues:** `QUEUE_CONNECTION=sync` by default (works with no worker). Horizon needs Redis + a daemon, so it is not shared-hosting friendly.
- **Realtime:** Reverb needs a long-running daemon and an open port. Not shared-hosting friendly. Set `BROADCAST_CONNECTION=log` / disable.
- **Search:** `SCOUT_DRIVER=mysql` works on shared hosting. Meilisearch/Elasticsearch need a service.
- **Writable:** `storage/`, `bootstrap/cache/`, `public/` (for `.htaccess`/favicons written by the installer), `.env`.
- **Mail:** SMTP outbound. Inbound by Mailgun/API webhook or Gmail Pub/Sub, or IMAP (`webklex/php-imap`) polled via the scheduler.
- **Settings location:** infrastructure in `.env`; product settings in the `settings` table (41 rows after seeding).
- **Shared hosting without SSH:** feasible only with `sync` queue, `file` cache/session, `mysql` Scout, no Reverb/Horizon, and the web installer or a pre-built package. Without SSH, `php artisan migrate` and `storage:link` must run via the installer or a cron/one-off route.

---

## 7. Feature matrix

| Feature | Verdict | Where | Coupling notes |
|---|---|---|---|
| Tickets, statuses, priority, groups, attachments | KEEP | `app/Conversations`, `routes/api.php` | Core |
| Agents, roles, groups, assignment | KEEP | `app/Team`, `common/.../Auth` | Core |
| Canned replies, internal notes | KEEP | `app/CannedReplies`, `ConversationItem::NOTE_TYPE` | |
| Knowledge base / help center | KEEP | `app/HelpCenter` | Also drives search |
| Customer portal, email notifications | KEEP | `CustomerTicketsController`, `Common\Notifications` | |
| Search, reports | KEEP | Scout, `app/Reports` | Uses Google Analytics Data package for traffic reports (REVIEW, optional) |
| Triggers / automation | KEEP (REVIEW) | `app/Triggers` | Contains the SSRF-prone web-request action |
| Custom attributes (fields) | KEEP | `app/Attributes` | **The extension point for `tijaraq_company_id`, `plan`, `channel`** |
| API (Sanctum) / Swagger | KEEP (REVIEW) | `public/swagger.yaml`, `/api/v1` | Needed for TijaraQ integration |
| Email piping (IMAP, Mailgun, Gmail, API) | KEEP | `app/Conversations/Email`, `app/Webhooks` | Fix S-02, S-04 |
| License activation UI + updater | **REMOVE** | `Core/Install/{LicenseController,Updater/*,UpdateController}`, `Commands/*Update*`, admin settings "license/updates" tabs | `SiteAlertsController`, `CommonServiceProvider.php:603`, `LoadSettingsManagerData.php:39` |
| Envato module (buyer verification, Envato login, updates server) | **REMOVE** | `modules/envato`, `config/modules.php`, `config/registration-rules.php`, `HelpDeskAutocompleteController::envatoItems`, `routes/api.php:171`, `LoadCustomerProfile`, `CustomerNewTicketPageDataController`, `app/Models/User.php`, `config/scout.php`, `database/migrations/*purchase_codes*`, `*envato*` | `Core/Modules.php` boots `EnvatoServiceProvider`; `Conversation`/customer UI show purchases |
| Billing (Stripe, PayPal, plans, invoices) | **REMOVE** | `common/.../Billing`, `BILLING_ENABLED=false`, billing routes/webhooks, admin settings UI | Many `common` classes reference products/subscriptions (`SubscriptionPolicy`, `ProductPolicy`, `InvoicePolicy`), so removal is deep. Prefer **disable via flag first** |
| Workspaces / multi-tenant | **REMOVE or leave disabled** | `common/.../Workspaces`, `WORKSPACES_ENABLED=false` | Already off. Leave disabled unless tidying |
| Custom domains | **REMOVE** | `common/.../Domains` | Google Safe Browsing / Phishtank calls |
| Social login (Facebook, Google, Twitter) | **REMOVE** (unless wanted) | `Auth/Controllers/SocialAuthController`, validators | Keep Google if SSO is routed via Google. See section 9 |
| AI Agent module | **REVIEW** | `modules/ai`, `config/prism.php`, `config/modules.php` | Separate paid item, uses admin-entered keys. Needs `ai` license decision |
| LiveChat module | **REVIEW** | `modules/livechat`, `public/livechat-loader.js` | Separate paid item. Daemon/WebSocket dependent. Uses `WIDGET_HMAC_SECRET` |
| Demo data + demo mode | **REMOVE** | `app/Demo/*`, `ResetDemoSite`, `routes/console.php:10`, `IS_DEMO_SITE`, `BlocksFunctionalityOnDemoSite`, `public/demo-files/` | `RestrictDemoSiteFunctionality` middleware and `blockOnDemoSite()` calls throughout controllers |
| Websockets (Reverb/Pusher/Ably) | **REVIEW/REMOVE for shared hosting** | `common/.../Websockets`, `config/reverb.php` | Realtime ticket updates degrade to polling |
| Horizon / Pulse / Telescope / Clockwork | **REMOVE** (Telescope/Clockwork are dev-only) | `laravel/horizon`, `laravel/pulse`, `public/vendor/{horizon,telescope}` | Horizon needs Redis + pcntl, unusable on shared hosting |
| Languages | **REVIEW** | `resources/lang/en` only | Only English is bundled. Bangla and Arabic must be added through the localization tool, and RTL support is UNVERIFIED (no RTL handling found server-side; check the React client) |
| Marketing "landing"/pages | **REVIEW** | `common/.../Pages`, `HcLandingPageController` | The KB landing page is useful; custom pages are generic |
| GDPR tools | **REVIEW** | not found as a dedicated feature | Open question 7 |

---

## 8. Removal plan (ordered)

1. **Phone-home and updater (lowest risk, highest value).** Replace `LicenseController` with a local no-op. Remove `CheckIfUpdateAvailableCommand` + schedule entry (`CommonServiceProvider.php:603`), `Updater/*`, `UpdateController` + routes, the admin "license/updates" tabs, and the license alert in `SiteAlertsController`. *Risk: low. Update-related views/commands may be referenced from the admin settings page.*
2. **Security fixes (S-01..S-09)**, before anything else is removed (Stage 2.2). Dependency updates first, then re-test.
3. **Envato module.** Remove `modules/envato`, its provider registration in `Core/Modules.php`, the `registration-rules.php` rule, the autocomplete route, the `User` model/purchase-code relations, the `purchase_codes` and `envato_username` migrations (squash), the customer-profile purchases panel, and the new-ticket envato-item selector. *Risk: medium. Touches registration, customer profile, and the new-ticket page.*
4. **Demo code.** Remove `app/Demo`, `ResetDemoSite`, the demo schedule, `IS_DEMO_SITE` branches, `public/demo-files`. *Risk: low, but `blockOnDemoSite()` calls are in many controllers.*
5. **Disable (don't delete) Billing, Workspaces, custom domains, social login** via config flags and menu/permission cleanup. Delete code only if time permits. *Risk: high if deleted: deep coupling inside `common`.*
6. **Drop Horizon / Pulse / Reverb on shared hosting** (`composer remove laravel/horizon laravel/pulse laravel/reverb`) and set queue/broadcast to `database`/`log`. *Risk: low-medium: references in the scheduler and bootstrap.*
7. **Migrations.** Squash after removals into one clean set; keep seeders for roles, statuses, priorities, default groups; read admin credentials from env. *Risk: medium: `MigrateAndSeed` runs migrations from `common` and modules, so squashing must preserve that order.*
8. **Configuration for TijaraQ** (single tenant, branding, departments, custom attributes, cron-driven queue).

---

## 9. Integration readiness (assessment only)

- **SSO from app.tijaraq.com: moderate effort.** There is no JWT/OIDC-provider endpoint. Options: (a) a small signed-URL auto-login controller in this app (HMAC signed, short expiry, creates or finds the user, then `Auth::login`), which is the simplest and fits cron-only hosting; (b) Socialite with a custom OAuth provider (the Envato provider is a worked example); (c) Sanctum personal access tokens via `auth/login` for API use. Recommendation: (a).
- **Ticket metadata:** the **custom attributes** system (`app/Attributes`) supports per-conversation fields with customer/agent visibility and permissions (`CustomAttribute::PERMISSION_*`). That is the natural home for `tijaraq_company_id`, `plan`, `channel`. Filtering/search by these fields is UNVERIFIED.
- **Ticket creation API:** `POST /api/v1/helpdesk/agent/conversations` (agent token) and `POST /api/v1/tickets/mail/incoming` (email API, needs `tickets.update`). A Sanctum token for a service user is enough for TijaraQ to raise tickets (e.g. sync-failure alerts).
- **Webhooks out:** triggers can POST to a URL on ticket events (`MakeWebRequestAction`), so TijaraQ can be notified of status changes. See S-12.
- **Branding:** logo/colors and email templates are database-driven from the admin UI (`Settings`, `MailTemplatePolicy`, `CssThemePolicy`), so no code change is needed. App name comes from `APP_NAME` (currently `BeDesk`).

---

## 10. Open questions for the founder

1. **Pristine vendor zip.** Can you supply the original CodeCanyon download? The on-disk copy was already edited, so a clean baseline commit is currently impossible.
2. **AI Agent and LiveChat modules** are separate paid items (item IDs 59719065, 59719106). Were both purchased? If not, they should be removed rather than used without a license.
3. **Mail intake:** which channel will you use for inbound tickets: forwarding to a mailbox (IMAP), Mailgun, Gmail Pub/Sub, or only the web form? This decides how S-02/S-04 are fixed.
4. **Hosting:** can the host provide SSH, Redis, or a long-running process? That decides Reverb/Horizon/Meilisearch.
5. **PHP version on the host** (recommend 8.3). PHP 8.5 locally already needs `--ignore-platform-req` flags and emits deprecations.
6. **Languages:** are you OK adding Bangla and Arabic as new translation sets (nothing bundled), and do you need confirmed RTL support?
7. **GDPR/data-export tooling:** do you need it? No dedicated feature was found.
8. **Public vs login-only ticket creation:** should guests be able to raise tickets (needs captcha + throttling), or only logged-in TijaraQ merchants via SSO?
9. **Social login / Google SSO:** keep or remove?
10. **Dependency update appetite:** OK to run a targeted `composer update` plus `npm audit fix` in Stage 2, re-testing afterwards?

---

*Stage 1 complete. Waiting for approval ("proceed with Stage 2", optionally with an edited REMOVE list). Nothing has been removed.*

---

# Cleanup results (Stage 2)

Branch `chore/helpdesk-audit-cleanup`. One commit per area; `git log --oneline` lists them. Everything below was verified on the local install unless marked otherwise.

## What was removed

| Area | Result |
|---|---|
| Vendor license check + remote self-updater | Deleted (`LicenseController`, `UpdateController`, `Updater/*`, update commands/views/routes, daily version check, license/update alerts, License and Updates tabs). **No code calls `support.vebto.com` any more.** The purchase code is read from `ENVATO_PURCHASE_CODE` in `.env` and is unused. Only documentation links to the vendor site remain in the admin UI. |
| Envato module | `modules/envato` deleted with every reference (server and React client): buyer purchase codes, Envato login, Envato items on tickets/profile/reports, settings page, `purchase_codes`/`envato_items` migrations. 15 routes removed (395 → 380). |
| Demo | `app/Demo/*`, the demo-reset command and schedule, `public/demo-files` deleted. |
| Vendor promos | Support links removed from the installer pages. |
| Dev-only dependency | `url-regex` (unused, high CVE) removed. |

## What was fixed

| Finding | Fix |
|---|---|
| S-01 dependencies | `composer audit`: 58 → 1 (low `firebase/php-jwt`, pinned by `google/apiclient`). `npm audit --omit=dev`: 19 → 0. |
| S-02 Gmail webhook | HMAC token required (`php artisan helpdesk:gmail-webhook-url`), payload validated. **Existing Pub/Sub subscriptions must be updated to the new URL.** |
| S-03 shared secrets | Removed from `env.example`; local Reverb credentials regenerated. Livechat identity hash is never signed with an empty key. |
| S-04 Mailgun | Signature check mandatory, constant-time compare. |
| S-05 updater routes | Removed with the updater. |
| S-06 headers | `SecurityHeaders` middleware: nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy, HSTS on HTTPS. |
| S-07 rate limits | Throttles on guest ticket creation (10/min), customer replies (30/min) and the inbound mail webhooks (120/min). |
| S-08 public uploads | SVG/HTML/XML/PHAR blocked on public upload types except branding images. |
| S-09 attachments | Allow-list: images, video, PDF, text/CSV/JSON/log, ZIP, Word/Excel. |
| S-10 attachment policy | Lookup now restricted to `conversationItem` rows. |
| S-12 SSRF | Trigger web requests only to public http(s) hosts. |
| S-15 defaults | `env.example` ships `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SAME_SITE=lax`. |

## TijaraQ configuration

- App name "TijaraQ Help", mail from "TijaraQ Support", websockets off, `QUEUE_CONNECTION=database` in `env.example` (drained by the scheduler, one cron line).
- Seeded departments (groups and ticket categories): Channels & Sync, Orders & Inventory, Couriers & Shipping, Billing & Plans, POS, Account & Access, General.
- Seeded agent-only, nullable conversation fields: `tijaraq_company_id`, `tijaraq_plan`, `tijaraq_channel` (Shopify / WooCommerce / Daraz / POS / Other).
- Locales `bn` and `ar` created (English text until translated). **No RTL support in the client.**

## Verification

| Check | Result |
|---|---|
| Fresh install on an empty DB | The test bootstrap drops every table in a scratch DB and runs the installer's migrate + seed path: no errors. `composer install --no-dev --dry-run`: lock file installable. `npx vite build`: succeeds (the `tsc` step still reports 33 pre-existing type errors, missing `@types/node` and livechat typings; baseline was 36). |
| Phone-home / license grep | No outbound call to `support.vebto.com`, `api.envato.com`, register-purchase-code or get-download-url in app code. |
| Obfuscation grep | No `eval`, `assert`, `create_function`, `shell_exec`, `exec`, `system`, `passthru`, `proc_open`, `popen`, `gzinflate`, `str_rot13` in the author's code. |
| Audits | See above. |
| `route:list` | No `envato`, `license`, `purchase`, `update` or demo routes. |
| Authorization tests | `tests/Feature/TicketAuthorizationTest.php`: 8 tests, 28 assertions pass. They cover owner access, customer B viewing/replying/closing/listing A's ticket, agent endpoints, and attachment download through both the default and the conversation policy. A deliberate regression in `ConversationFileEntryPolicy` and in `ConversationPolicy` was detected by the tests. PHPUnit reports them "risky" because Laravel replaces PHP error handlers; this is cosmetic. |
| `/install` after install | Routes are only registered when `INSTALLED` is not true (verified in code, `web.php`). Not exercised against a second install. |
| Manual smoke test in a browser | **Not done.** Admin login through the UI, ticket creation with attachment, email notification, KB search and the new settings pages were not clicked through; only HTTP-level checks and the PHPUnit tests ran. |

## Remaining known risks / not done

- **Not deleted (left disabled, deep coupling in `common/`):** Billing (Stripe/PayPal, `BILLING_ENABLED=false`), Workspaces, custom domains, Facebook/Google/Twitter social login, Horizon, Pulse, Reverb, Clockwork, plus the client UI of the AI and LiveChat modules (their PHP backends are not in this package, so they are inactive).
- **Migrations were not squashed.** Only migrations that depended on the removed Envato module were deleted.
- **No CSP header.** The SPA uses inline bootstrap data, so a safe policy needs a nonce/hash design.
- Forgot-password throttling was not verified or added (S-07 residual).
- S-11 (enumerable file hashes), S-13 (Reverb `allowed_origins`) and S-14 (middleware calls inside actions) were left as low-risk and unchanged.
- **New observation:** the admin settings endpoint returns the parsed `.env` (including secrets) to admins by design (`LoadSettingsManagerData::loadEnvSettings`, partly masked by `RedactSensitiveSettings`). Admin accounts must be treated as holding every secret in `.env`.
- Attachment allow-list is a behavior change: other types (for example `.exe`, `.7z`, `.rar`) are now refused. Extend `config/filesystems.php` if you need them.
- Mailgun inbound without `MAILGUN_SECRET` is now rejected.
- `.env` was edited during this session (APP_NAME, cookies, queue, Telescope off, regenerated Reverb creds); `APP_URL` is now `http://help.tijaraq.test` (rewritten by the app when Herd served it).
- The PHP 8.5 deprecation noise from vendor packages is hidden, not fixed.
