<?php

namespace App\Webhooks\Controllers;

use App\Conversations\Email\Parsing\ParsedEmail;
use App\Conversations\Email\TransformEmailIntoTicketOrReply;
use App\Conversations\Email\Transformers\MimeMailTransformer;
use Common\Core\BaseController;
use Common\Settings\Mail\GmailClient;

class GmailWebhookController extends BaseController
{
    /**
     * Shared secret that must be appended to the Pub/Sub push endpoint as
     * "?token=...". Print it with: php artisan helpdesk:gmail-webhook-url
     */
    public static function expectedToken(): string
    {
        return hash_hmac('sha256', 'gmail-webhook', config('app.key'));
    }

    public function handle()
    {
        $this->blockOnDemoSite();

        if (!hash_equals(self::expectedToken(), (string) request('token'))) {
            abort(403);
        }

        $payload = json_decode(
            (string) base64_decode((string) request()->input('message.data')),
            true,
        );
        $newHistoryId = is_array($payload) ? $payload['historyId'] ?? null : null;

        if (!$newHistoryId || !is_scalar($newHistoryId)) {
            return $this->error('Invalid payload.', [], 422);
        }

        $tokenPath = GmailClient::tokenPath();
        if (!file_exists($tokenPath)) {
            return $this->success();
        }

        $token = json_decode(file_get_contents($tokenPath), true);
        $lastHistoryId = $token['lastHistoryId'] ?? null;
        $token['lastHistoryId'] = $newHistoryId;
        file_put_contents($tokenPath, json_encode($token));

        if ($lastHistoryId) {
            $emails = app(GmailClient::class)->listHistory($lastHistoryId);
            foreach ($emails as $email) {
                $decoded = base64_decode(
                    str_replace(['-', '_'], ['+', '/'], $email->getRaw()),
                );
                $emailData = (new MimeMailTransformer())->transform($decoded);

                (new TransformEmailIntoTicketOrReply(
                    new ParsedEmail($emailData),
                ))->execute();
            }
        }

        return $this->success();
    }
}
