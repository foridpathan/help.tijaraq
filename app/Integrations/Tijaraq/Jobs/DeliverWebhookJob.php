<?php

namespace App\Integrations\Tijaraq\Jobs;

use App\Integrations\Tijaraq\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * POSTs one signed webhook to the main app.
 *
 * Signature: hex HMAC-SHA256(webhook secret, timestamp . "." . raw_body)
 * The secret is never logged or stored; error strings only contain the
 * status code or the transport error.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    public function __construct(public int $deliveryId)
    {
        $this->tries = (int) config('tijaraq-integration.webhook_tries', 6);
    }

    public function backoff(): array
    {
        return config('tijaraq-integration.webhook_backoff', [
            60, 300, 900, 3600, 10800,
        ]);
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);
        if (!$delivery || $delivery->status === WebhookDelivery::DELIVERED) {
            return;
        }

        $url = config('tijaraq-integration.webhook_url');
        $secret = config('tijaraq-integration.webhook_secret');
        if (!$url || !$secret) {
            throw new RuntimeException('Webhook URL or secret is not configured.');
        }

        $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();

        $delivery->increment('attempts');

        try {
            $response = Http::timeout(
                (int) config('tijaraq-integration.webhook_timeout', 10),
            )
                ->withHeaders([
                    'X-Tijaraq-Event' => $delivery->event,
                    'X-Tijaraq-Event-Id' => $delivery->event_id,
                    'X-Tijaraq-Timestamp' => $timestamp,
                    'X-Tijaraq-Signature' => hash_hmac(
                        'sha256',
                        $timestamp . '.' . $body,
                        $secret,
                    ),
                ])
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (Throwable $e) {
            $delivery->update([
                'last_status_code' => null,
                'last_error' => substr(class_basename($e) . ': ' . $e->getMessage(), 0, 500),
            ]);
            throw $e;
        }

        if ($response->successful()) {
            $delivery->update([
                'status' => WebhookDelivery::DELIVERED,
                'last_status_code' => $response->status(),
                'last_error' => null,
                'delivered_at' => now(),
            ]);
            return;
        }

        $delivery->update([
            'last_status_code' => $response->status(),
            'last_error' => 'Receiver answered HTTP ' . $response->status(),
        ]);

        // any non-2xx is retried
        throw new RuntimeException('Webhook receiver answered HTTP ' . $response->status());
    }

    public function failed(Throwable $e): void
    {
        WebhookDelivery::query()
            ->where('id', $this->deliveryId)
            ->update([
                'status' => WebhookDelivery::FAILED,
                'last_error' => substr($e->getMessage(), 0, 500),
                'updated_at' => now(),
            ]);

        report($e);
    }
}
