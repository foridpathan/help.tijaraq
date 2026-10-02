# TijaraQ support chat and AI assistant

## Where to find them

- Customers: **Livechat** in the help center header, or `/livechat`. Sign in first.
- Agents: **Livechat** and **AI assistant** in the dashboard sidebar, at `/dashboard/livechat` and `/dashboard/ai-assistant`.
- Administrators: **Settings → Livechat** at `/admin/settings/livechat` enables or disables new messages. **Settings → AI assistant** at `/admin/settings/ai` selects a provider, model, and API key.

## How chat works

The customer starts a chat and writes a message. This creates a conversation in the existing helpdesk inbox with the `livechat` channel. Agents can reply from the Livechat screen or the conversation inbox. Chat changes are broadcast immediately to private customer and agent channels through Laravel Echo; regular helpdesk events still use the queue. A 60-second reconciliation request handles missed chat events. Until broadcasting is configured, the pages poll every 30 seconds. Existing ticket assignment and email notification rules apply. Signing in is required; this is not an anonymous embedded website widget.

For shared hosting, configure Pusher Channels in **Settings → System → Websockets**, or set `BROADCAST_CONNECTION=pusher` and the `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, and `PUSHER_APP_CLUSTER` environment values. Set `WEBSOCKETS_INTEGRATED=true` to show the settings page. The chat event broadcasts immediately, so it does not wait for the minute-based queue cron; the regular helpdesk broadcasts still need a queue worker or cron. A VPS may use Laravel Reverb instead, with its server process kept running. Without Pusher credentials or a running Reverb server, real-time delivery cannot start.

## How AI works

Choose OpenAI, Anthropic, Gemini, or OpenRouter, enter a model supported by that provider, and save its API key. The key is stored server side in the helpdesk `.env`; settings responses return only whether a key is configured. Agents can ask for a draft, optionally supplying a conversation ID. The latest 12 messages of that conversation are sent to the selected provider as context. The agent reviews, edits, and sends the answer manually. AI never sends a customer reply on its own.

The older `modules/ai` agent/campaign UI and `modules/livechat` embedded widget UI have no corresponding backend in this checkout. These new pages and API routes are the usable support chat and draft assistant; they do not activate those old features.

## TijaraQ application connection

The main TijaraQ app uses the signed `/api/integration/v1` API, SSO token issuer, and webhook endpoint. Keep those API routes. Set matching integration secrets, SSO keys, and URLs in both apps for each deployment environment. The local development connection uses the helpdesk on `127.0.0.1:8000` and the main application on `localhost:8001`. The generic `/api-docs` page and Developer menus are removed.
