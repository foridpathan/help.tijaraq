<?php

namespace App\Integrations\Tijaraq\Console;

use App\Integrations\Tijaraq\Models\WebhookDelivery;
use App\Integrations\Tijaraq\Services\WebhookDispatcher;
use Illuminate\Console\Command;

class RetryWebhooksCommand extends Command
{
    protected $signature = 'tijaraq:webhooks:retry {--failed : Requeue failed deliveries}';

    protected $description = 'Requeue TijaraQ webhook deliveries that failed or got stuck';

    public function handle(WebhookDispatcher $dispatcher): int
    {
        $query = WebhookDelivery::query();

        if ($this->option('failed')) {
            $query->where('status', WebhookDelivery::FAILED);
        } else {
            // pending for a while: the job was lost or the queue was down
            $query
                ->where('status', WebhookDelivery::PENDING)
                ->where('created_at', '<', now()->subMinutes(15));
        }

        $count = 0;
        $query->orderBy('id')->each(function (WebhookDelivery $delivery) use (
            $dispatcher,
            &$count,
        ) {
            $dispatcher->requeue($delivery);
            $count++;
        });

        $this->info("Requeued $count webhook deliveries.");

        return self::SUCCESS;
    }
}
