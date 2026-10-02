# Performance review — 2026-10-02

This review covers the application request paths and client polling visible in the checkout. It is a static review plus a production build and feature tests; no production traffic profile or database query plan was available.

## Changes made

- Livechat previously requested the conversation list every 5 seconds and the open thread every 4 seconds: about 27 HTTP requests per minute per open tab. Chat changes now publish a small immediate broadcast to private customer and agent channels. The page uses Laravel Echo to refresh the active thread on a message and the list on creation, with a 60-second reconciliation interval when broadcasting is configured. With no broadcaster configured, it polls every 30 seconds: 4 requests per minute per open tab.
- The chat thread query previously returned the oldest 200 messages. It now returns the newest 200 in display order. The livechat list filters have composite indexes for agent and customer lookups.
- The example Pusher environment names now match the Laravel broadcasting configuration.
- Chat broadcasts are immediate because this deployment may drain the database queue only once per minute. Broadcast transport failures are logged and do not fail a saved message.
- The HTML purifier cache directory is now kept in the checkout so clean installs can write the cache; its generated contents remain ignored.

## Other paths checked

- TijaraQ integration ticket and message endpoints paginate and scope queries by verified tenant. The ticket presenter loads related models in batches. The metadata endpoint uses a 10-minute cache. No clear N+1 query or unbounded response was found in these paths.
- The AI assistant limits conversation context to 12 messages. Its provider request cost and latency need measurement under real traffic; no speculative cache was added.
- The queue statistics page polls every 5 seconds, but it is an admin-only screen. Agent presence uses a 30-second refresh. These were left as operational or presence updates.
- The production build reports several JavaScript chunks above 500 KB, including the editor and icon list. These features are split into separate assets. Measure first-load traffic and browser coverage before changing the bundle layout.

## Deployment checks

Run the new migration. Configure Pusher or Reverb to enable immediate chat updates. Verify a customer and an agent in separate browsers, including a disconnected websocket and a reply sent from the regular inbox. Monitor Pusher connection and message usage, HTTP request rate, queue lag, and database query latency after deployment.

## Verification

The production frontend build and the targeted chat feature tests pass. The broader PHP suite still has seven integration test failures unrelated to livechat; examples include idempotency replay comparing JSON field order and webhook assertions about recorded requests. Those failures need separate investigation before using the full suite as a release gate. PHPUnit also reports many tests as risky because of the existing test configuration or code.
