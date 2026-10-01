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

        if ($url && !$this->isPublicHttpUrl($url)) {
            report(new Exception("Blocked trigger web request to $url"));
            return $conversation;
        }

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

    // SSRF guard: only http(s) to hosts that resolve to public addresses
    protected function isPublicHttpUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (
            !$parts ||
            !in_array($parts['scheme'] ?? '', ['http', 'https'], true) ||
            empty($parts['host'])
        ) {
            return false;
        }

        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : (gethostbynamel($host) ?: []);

        if (!$ips) {
            return false;
        }

        foreach ($ips as $ip) {
            if (
                !filter_var(
                    $ip,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
                )
            ) {
                return false;
            }
        }

        return true;
    }
}
