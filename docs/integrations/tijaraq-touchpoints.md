# TijaraQ integration: vendor touchpoints

Everything new lives in `app/Integrations/Tijaraq/` (not overwritten by a vendor zip).
This file lists the **vendor files that were edited** so they can be re-applied after
a vendor zip upgrade, plus the vendor internals the integration relies on.

## Edited vendor files

| File | Change |
|---|---|
| `bootstrap/app.php` | one `use` line and one entry (`TijaraqIntegrationServiceProvider::class`) in `providers:` |
| `common/foundation/config/sanctum.php` | `authenticate_session` now uses `App\Core\Middleware\AuthenticateSession` (new file, see below) instead of Sanctum's class |
| `common/foundation/src/Settings/Manager/RedactSensitiveSettings.php` | `tijaraq_*` secret names added to `$serverKeys` |
| `database/seeders/DatabaseSeeder.php` | calls `TijaraqIntegrationSettingsSeeder` |
| `env.example` | blank `APP_KEY`, `TRUST_ALL_PROXIES=false` + `TRUSTED_PROXIES` note, `TIJARAQ_*` variables |
| `phpunit.xml` | `TIJARAQ_INTEGRATION_ENABLED=true` for the test run |

No change was made to `composer.json`, `ConversationPolicy`, `AppServiceProvider`,
Fortify classes, or any client (TypeScript) file for the integration.

New files outside `app/Integrations/Tijaraq/` (not vendor, but easy to forget):

* `app/Core/Middleware/AuthenticateSession.php`: fixes the "logged out on reload"
  bug. Sanctum 4.2 compares the raw password hash with the session value while
  Laravel 12 stores an HMAC. This subclass of Laravel's own middleware understands
  both and always asks the `web` session guard. If a future Sanctum release fixes
  this, `sanctum.php` can point back to `Laravel\Sanctum\Http\Middleware\AuthenticateSession`.
* `config/tijaraq-integration.php`
* `database/migrations/2026_10_02_1000*`, `2026_10_02_1001*`, `2026_10_02_1002*` (additive)
* `database/seeders/TijaraqIntegrationSettingsSeeder.php`
* `tests/Feature/Integration/*`, `tests/Unit/TijaraqCanonicalRequestTest.php`
* `docs/integrations/*`

## How the integration hooks into the vendor code without editing it

| Need | Mechanism |
|---|---|
| Routes | the service provider loads `app/Integrations/Tijaraq/routes/{integration,sso}.php` |
| Webhooks for status changes made by triggers | `OnConversationsUpdated` is registered in the provider's **`register()`** (before `AppServiceProvider::registerEvents()` runs in `boot()`). The vendor listener returns `false` while `TriggersCycle::$isRunning`, which stops every *later* listener; ours is earlier, so it still runs. No vendor file is edited and no `dispatch()` hook is needed. If the vendor ever moves its listener into a provider that registers earlier, re-check `WebhooksTest::test_a_status_change_made_by_a_trigger_still_emits` |
| `external_company_id` / `priority` on new tickets | `Conversation::creating` hook in the provider, fed by `CreationHints` while `CreateTicketAsCustomer` runs |
| Password route hardening and lockdown | `HardenPasswordRoutes` middleware appended to the `web` and `api` groups (it only acts on `auth/login`, `api/v1/auth/login`, `auth/forgot-password`, `auth/reset-password`, `api/v1/auth/password/email`) |
| Contract error format | `renderable()` callback on the exception handler for `api/integration/*` |
| Scheduler | the provider adds a daily idempotency prune to the schedule |

## Vendor internals the integration depends on

Re-run `php artisan test` after a vendor upgrade, these tests exercise all of them.

* `Conversation` columns (`type`, `status_category`, `priority`, `group_id`,
  `assignee_id`), `Conversation::changeStatus`, status categories 6/5/4/3.
* `CreateTicketAsCustomer`, `SubmitMessageAsCustomer`, `ConversationEventsCreator`
  signatures. The integration passes whitelisted arrays only, never request data.
* `ConversationsUpdated` (constructor snapshot, `conversationsDataBeforeUpdate`,
  `conversationsAfterUpdate`), `ConversationMessageCreated`,
  `ConversationsAssignedToAgent`.
* `ConversationsUpdated::pauseDispatching()` is called by the vendor ticket creation
  and never resumed. `CreateTicket` calls `resumeDispatching()` after the vendor action
  so later changes in the same process still emit events.
* `file_entry_models` pivot: rows with `model_type='user'` are only the ownership
  pivot, `model_type='conversationItem'` + `relation_type='attachments'` are message
  attachments.
* `FileEntryPayload`, `FileUploadValidator`, `StoreFile`, `CreateFileEntry`,
  `FileResponseFactory`, upload type `conversationAttachments`.
* `CustomAttribute` keys `category` (dropdown options = ticket categories) and
  `tijaraq_company_id` (read-only display for agents), table `attributables`.
* Fortify route paths and response contracts (`SuccessfulPasswordResetLinkRequestResponse`,
  `FailedPasswordResetResponse`).
* User columns `type` ('agent' marks staff) and the `admin` permission.

## Queue / cron

Webhook delivery is queued. The queue is drained by the per-minute cron
(`schedule:run`), so expect up to about 60 s of webhook latency. `tijaraq:webhooks:retry`
requeues stuck or failed deliveries.

## Production checklist

* Confirm the production `APP_KEY` differs from the old template value
  (`base64:NE5wdzVE...`). The template no longer ships one.
* Set `TRUSTED_PROXIES` when behind a proxy/CDN.
* Multi-node: use a shared cache store for nonces and SSO `jti`.
* Run `php artisan migrate` and `php artisan db:seed --class='Database\Seeders\TijaraqIntegrationSettingsSeeder'`.
