<?php namespace App\Triggers\Actions;

use App\Conversations\Models\Conversation;
use App\Triggers\Models\Trigger;
use Common\Core\Prerender\Actions\ReplacePlaceholders;
use Exception;
use Illuminate\Support\Facades\Http;

class MakeWebRequestAction implements TriggerActionInterface
{
    public function execute(
        Conversation $conversation,
        array $action,
        Trigger $trigger,
    ): Conversation {
        $url = $action['value']['url'] ?? null;

        if ($url) {
            $payload = app(ReplacePlaceholders::class)->execute(
                $action['value']['payload'] ?? '',
                [
                    'conversation' => $conversation->toArray(),
                ],
            );

            try {
                Http::throw()->post($url, $payload);
            } catch (Exception $e) {
                report($e);
            }
        }

        return $conversation;
    }
}
