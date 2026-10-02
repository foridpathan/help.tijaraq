# TijaraQ integration (help.tijaraq.com <-> app.tijaraq.com)

help.tijaraq.com stays the single source of truth for support tickets. The
TijaraQ main app calls a signed server-to-server API on behalf of merchants,
merchants reach the helpdesk through SSO (they never register or type a
password), and the helpdesk sends signed webhooks back.

All code lives in `app/Integrations/Tijaraq/` (PSR-4 `App\Integrations\Tijaraq`),
so a vendor zip upgrade does not overwrite it. Vendor files that were touched are
listed in [tijaraq-touchpoints.md](tijaraq-touchpoints.md).

The integration is switched on with `TIJARAQ_INTEGRATION_ENABLED=true`. When it is
`false` no route, listener or scheduled task is registered (only the password
route hardening described below stays active).

## Environment

```
TIJARAQ_INTEGRATION_ENABLED=true
TIJARAQ_API_KEYS={"k1":"<64+ char random>"}     # JSON map; add k2 during rotation, remove k1 after
TIJARAQ_WEBHOOK_URL=https://app.tijaraq.com/webhooks/helpdesk
TIJARAQ_WEBHOOK_SECRET=<64+ char random>
TIJARAQ_SSO_ISSUER=https://app.tijaraq.com
TIJARAQ_SSO_AUDIENCE=https://help.tijaraq.com
TIJARAQ_SSO_PUBLIC_KEYS_PATH=storage/app/private/tijaraq-sso/   # <kid>.pem files
TIJARAQ_ALLOWED_IPS=                                          # empty = allow-list off
TIJARAQ_RATE_LIMIT_PER_TENANT=120                             # per minute
```

`config/tijaraq-integration.php` reads these. Secrets are environment-only: they are
never stored in the `settings` table or the database and are never logged. The
secret names are also listed in `RedactSensitiveSettings` so the admin settings
endpoint can never show them.

Generate secrets with e.g. `php -r "echo bin2hex(random_bytes(48));"`.

## Integration Contract v1

Identical copy in the main app repo. Do not diverge.

### Request signing (main app -> helpdesk)

Base URL: `https://help.tijaraq.com/api/integration/v1`

| Header | Value |
|---|---|
| `X-Tijaraq-Key-Id` | key id, e.g. `k1` (supports rotation) |
| `X-Tijaraq-Timestamp` | unix seconds; accepted within ±300 s |
| `X-Tijaraq-Nonce` | UUIDv4; single-use for 10 min |
| `X-Tijaraq-Tenant` | main app `creatorId()` (company id) |
| `X-Tijaraq-User` | main app user id |
| `X-Tijaraq-User-Email` | user email |
| `X-Tijaraq-User-Name` | base64url(UTF-8 name) |
| `X-Tijaraq-Email-Verified` | `1` or `0` |
| `X-Tijaraq-Scope` | `own` or `company` |
| `X-Tijaraq-Request-Id` | UUIDv4, for audit/log correlation |
| `X-Tijaraq-Idempotency-Key` | UUIDv4. Required on `POST /tickets` and `POST /tickets/{id}/replies` |
| `X-Tijaraq-Signature` | lowercase hex HMAC-SHA256 of the canonical string |

Canonical string (parts joined by `\n`):

```
UPPERCASE_METHOD
/api/integration/v1/<path>            (no trailing slash)
<query: keys sorted, RFC3986-encoded, k=v joined by &; empty if none>
<every x-tijaraq-* header except signature: lowercased name, sorted BY NAME, "name:trimmed-value", joined by \n>
<sha256 hex of raw request body; sha256("") for GET; for multipart, sha256 of the raw file bytes>
```

Details that matter for interoperability:

* Headers are sorted by lowercase **name** (`x-tijaraq-user` sorts before
  `x-tijaraq-user-email`). Sorting the finished `name:value` lines gives a different
  order, do not do that.
* Query pairs are percent-decoded (`+` is a space), sorted by key then value, then
  re-encoded with RFC3986 (`rawurlencode`).
* Fixed test vectors are in `tests/Unit/TijaraqCanonicalRequestTest.php` (produced by
  an independent implementation). Copy them into the main app tests.
* Comparison uses `hash_equals`. The secret is looked up by `Key-Id`.
* A nonce is only consumed by a request with a valid signature.

### Endpoints

| Method & path | Body / query | Response |
|---|---|---|
| `GET /meta` | – | `{departments:[{id,name}], categories:[{value,label}], priorities:[{value,label}], statuses:[{key,label}]}` |
| `GET /tickets` | `status=open\|pending\|closed`, `search`, `page`, `per_page≤50` | `{data:[Ticket], meta:{current_page,last_page,per_page,total}}` |
| `POST /tickets` | `{subject≤191, body_html≤50000, category, department_id?, priority, attachment_ids[]≤10}` | `201 Ticket` |
| `GET /tickets/{id}` | – | `Ticket` |
| `GET /tickets/{id}/messages` | `page` | `{data:[Message], meta}`, oldest first |
| `POST /tickets/{id}/replies` | `{body_html, attachment_ids[]≤10}` | `201 Message` |
| `POST /tickets/{id}/close` | – | `Ticket` |
| `POST /tickets/{id}/reopen` | – | `Ticket` (`ticket_locked` if locked) |
| `POST /attachments` | multipart `file` (helpdesk allow-list, 25 MB) | `201 {id,name,size,mime}` |
| `GET /attachments/{id}` | – | file stream with `Content-Disposition` |

* `priority` is one of `low|medium|high|urgent` (stored as 1/2/3/4).
* `Ticket` = `{id, subject, status:"open|pending|closed|locked", status_label, priority, category, department:{id,name}|null, assignee_name|null, requester:{external_user_id,name}, last_reply_by:"customer|agent"|null, last_reply_at, created_at, updated_at}`
* `Message` = `{id, author_type:"customer|agent|system", author_name, body_html, attachments:[{id,name,size,mime}], created_at}`
* Internal notes (and ticket events) are never returned.
* `status` also accepts `locked` as a list filter.

Errors always use `{error:{code,message,details?}}`:

| HTTP | codes |
|---|---|
| 401 | `invalid_signature`, `stale_timestamp`, `replayed_nonce`, `unknown_key` |
| 403 | `ip_not_allowed` |
| 404 | `not_found` (also for cross-tenant ids: a ticket's existence never leaks) |
| 409 | `idempotency_conflict`, `ticket_locked` |
| 422 | `validation_failed` (`details` = field => messages) |
| 429 | `rate_limited` (with `Retry-After`) |
| 503 | `maintenance` |

Idempotency: same `(tenant, key)` + same request returns the original response with
`X-Tijaraq-Idempotent-Replay: 1`; same key with a different request is `409`. Keys
live 24 h and are pruned daily by the scheduler. A failed request does not consume
the key.

### Visibility and mapping

* `scope=own`: only tickets the caller created. `scope=company`: every ticket of the
  tenant. Cross-tenant ids are always `404`.
* Statuses seen by tenants: `open | pending | closed | locked` (status category
  6 / 5 / 4 / 3). `status_label` is the helpdesk status name.
* A customer reply re-opens a ticket (vendor behaviour). Replying to a locked ticket
  is `409 ticket_locked`.
* The agent UI only knows Low / Normal / High, so `urgent` (4) is displayed as
  "High" there.

### Webhooks (helpdesk -> main app)

`POST https://app.tijaraq.com/webhooks/helpdesk` with headers `X-Tijaraq-Event`,
`X-Tijaraq-Event-Id` (UUID), `X-Tijaraq-Timestamp`, `X-Tijaraq-Signature` =
hex HMAC-SHA256(**webhook secret**, `timestamp + "." + raw_body`). The webhook secret is
separate from the API secrets. The receiver must answer 2xx within 5 s; anything else
is retried (6 tries, back-off 1 min, 5 min, 15 min, 1 h, 3 h).

Events: `ticket.replied` (agent replies only, never notes), `ticket.status_changed`,
`ticket.assigned`.

```
{event_id, event, occurred_at, external_company_id, external_user_id, ticket: Ticket, data:{...}}
data: replied {message: Message} | status_changed {from,to} | assigned {assignee_name}
```

Notes for the receiver:

* Retries re-send the same body and the same `event_id`: de-duplicate on `event_id`.
* `ticket.status_changed` is emitted when the tenant-visible key changes
  (open/pending/closed/locked). Moving between two helpdesk statuses of the same
  category is not reported.
* `ticket.assigned` can reach the helpdesk through two internal events. Both build the
  same deterministic `event_id` from (conversation, event, new assignee, updated_at)
  and are de-duplicated. If an assignment crosses a second boundary between the two
  paths the receiver may still see it twice, de-duplicate on `event_id`.
* Only tickets that carry an `external_company_id` emit webhooks.
* Delivery rides the queue. The queue is drained by the per-minute cron, so expect up
  to about 60 s of latency.

### SSO (main app -> helpdesk browser redirect)

`GET https://help.tijaraq.com/sso/tijaraq?token=<JWT>&redirect=<relative path>`

JWT is RS256 with a `kid` header and the claims `iss=https://app.tijaraq.com`,
`aud=https://help.tijaraq.com`, `sub` (external user id), `cid` (company id), `email`,
`email_verified`, `name`, `scope`, `jti` (single-use), `iat`, `nbf`, `exp`
(≤ 60 s after `iat`; 10 s leeway). `redirect` must start with `/` and not `//`;
anything else falls back to `/hc/tickets`.

On success the customer is provisioned, signed in (session id regenerated), and
redirected. On any failure the visitor gets a friendly page with an "Open from your
TijaraQ dashboard" link to `https://app.tijaraq.com/support`; the reason is only
logged (`storage/logs`) and audited, never shown.

### Customer provisioning (JIT)

Done for every signed API call and every SSO login (`CustomerProvisioner`):

1. Existing user with the same `external_user_id`: refresh name, company and (if
   verified and free) email. Never touches `type` or roles.
2. Otherwise, a user with the same email:
   * agent or admin: **never linked**, a separate customer is created;
   * non-staff, `email_verified=1`, no external identity yet: linked;
   * anything else: not linked.
3. Otherwise a new customer: verified email becomes the primary email; an unverified
   address (or a collision) creates the user with `email=null` and keeps the address
   as a secondary email.

### Login lockdown

Customers with `external_source='tijaraq'` and no agent/admin role cannot use
password login (`/auth/login`, `/api/v1/auth/login`), forgot-password or
reset-password. They get the same generic response as a stranger (no account
enumeration). Agents and admins keep normal login. Forgot/reset password are
throttled to 5 attempts per minute per IP + email for everyone.

`Database\Seeders\TijaraqIntegrationSettingsSeeder` (idempotent, also called from
`DatabaseSeeder`) sets `registration.disable=1`, `tickets.guest_tickets=0`, disables
every `social.*.enable`, and adds the "Onboarding Call" group and category option.

## Runbooks

### API key rotation (k1 -> k2)

1. Generate a new secret. Set `TIJARAQ_API_KEYS={"k1":"<old>","k2":"<new>"}` on the
   helpdesk and `php artisan config:clear`.
2. Switch the main app to key id `k2` with the new secret.
3. Watch the audit log / error rate. When no request uses `k1` any more, remove it
   from `TIJARAQ_API_KEYS`.

### SSO key rotation (by `kid`)

1. Drop the new public key as `<new-kid>.pem` into `TIJARAQ_SSO_PUBLIC_KEYS_PATH`
   (the old one stays).
2. The main app starts signing with the new key and `kid`.
3. Delete the old `<kid>.pem` after tokens signed with it have expired (> 60 s).

`kid` may only contain `A-Z a-z 0-9 . _ -`; anything else is rejected.

### Webhook secret rotation

The secret is a single value. Change it on both sides in a maintenance window, or
let the main app accept both values for a while.

### Webhook retry

Failed deliveries (after all retries) have `status=failed` in
`tijaraq_webhook_deliveries`:

```
php artisan tijaraq:webhooks:retry --failed   # requeue failed deliveries
php artisan tijaraq:webhooks:retry            # requeue deliveries stuck "pending" for > 15 min
```

### Troubleshooting with `X-Tijaraq-Request-Id`

Every mutating call and every attachment download writes a row to
`tijaraq_audit_logs` (`external_company_id`, `external_user_id`, `action`,
`conversation_id`, `request_id`, `ip`). Search by the request id the main app logged:

```sql
SELECT * FROM tijaraq_audit_logs WHERE request_id = '<uuid>';
```

SSO failures are audited as `sso.failed` (reason, truncated) and logged as
"TijaraQ SSO rejected". Delivery history is in `tijaraq_webhook_deliveries`
(`attempts`, `last_status_code`, `last_error`).

Common causes: `stale_timestamp` = clock drift (use NTP); `invalid_signature` =
canonical string mismatch (compare against the test vectors, check header sorting
and query encoding); `replayed_nonce` = a retry re-used a nonce, generate a new
nonce per attempt; `ip_not_allowed` = `TIJARAQ_ALLOWED_IPS` or proxy trust
(`TRUSTED_PROXIES`) is wrong.

### Operational notes

* The nonce / SSO `jti` store uses the application cache. The file cache is enough
  for one node. **Multi-node deployments must use a shared cache store** (redis,
  database, memcached) or a nonce can be replayed on another node.
* Behind a proxy or CDN set `TRUSTED_PROXIES` so `$request->ip()` is the real client
  IP (allow-list and rate limits depend on it).
* Confirm the production `APP_KEY` differs from the old template value (the template
  no longer ships one; the installer generates it).
* Rate limits: 600 requests/min per IP (before authentication) and
  `TIJARAQ_RATE_LIMIT_PER_TENANT` per minute per verified tenant.

## Tests

`php artisan test` (PHPUnit, database `help_tijrak_test`) covers HMAC verification
(tampering of body/path/query/every signed header, stale timestamp, replayed nonce,
unknown key, rotation), tenant isolation, idempotency, attachments, notes,
provisioning, SSO, login lockdown and webhooks.
Run `DB_DATABASE=help_tijrak_test php artisan migrate` once before the first run.
