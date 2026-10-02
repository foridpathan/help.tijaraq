<?php

namespace Tests\Feature\Integration;

use App\Conversations\Agent\Actions\ConversationsAssigner;
use App\Conversations\Agent\Actions\SubmitMessageAsAgent;
use App\Conversations\Events\ConversationMessageCreated;
use App\Conversations\Messages\CreateConversationMessage;
use App\Conversations\Models\Conversation;
use App\Conversations\Models\ConversationStatus;
use App\Integrations\Tijaraq\Models\WebhookDelivery;
use App\Triggers\TriggersCycle;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class WebhooksTest extends IntegrationTestCase
{
    protected const URL = 'https://app.test/webhooks/helpdesk';
    protected const SECRET = 'webhook-secret-webhook-secret-webhook-secret-webhook-secret-1234';

    protected array $identity1;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tijaraq-integration.webhook_url' => self::URL,
            'tijaraq-integration.webhook_secret' => self::SECRET,
        ]);
        $this->receiverReturns(200);
        Mail::fake();

        $this->identity1 = $this->uniqueIdentity(['tenant' => 'company-wh']);
    }

    // fresh fake each time: with Http::fake() the first matching stub wins
    protected function receiverReturns(int $status): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([self::URL => Http::response('receiver', $status)]);
    }

    protected function ticket(): Conversation
    {
        return Conversation::find($this->createTicketId($this->identity1));
    }

    /** @return array<int, Request> */
    protected function sent(?string $event = null): array
    {
        return Http::recorded()
            ->map(fn($pair) => $pair[0])
            ->filter(
                fn(Request $r) => !$event ||
                    $r->header('X-Tijaraq-Event')[0] === $event,
            )
            ->values()
            ->all();
    }

    protected function agentReply(Conversation $ticket, string $body = '<p>Hello from support</p>'): void
    {
        $agent = $this->makeAgent();
        $this->actingAs($agent);
        (new SubmitMessageAsAgent())->execute($ticket, ['body' => $body]);
    }

    public function test_an_agent_reply_emits_a_signed_ticket_replied_webhook(): void
    {
        $ticket = $this->ticket();

        $this->agentReply($ticket);

        $requests = $this->sent('ticket.replied');
        $this->assertCount(1, $requests);

        /** @var Request $request */
        $request = $requests[0];
        $body = $request->body();
        $payload = json_decode($body, true);

        // signature = hex HMAC-SHA256(secret, timestamp . "." . raw body)
        $timestamp = $request->header('X-Tijaraq-Timestamp')[0];
        $this->assertEqualsWithDelta(time(), (int) $timestamp, 5);
        $this->assertSame(
            hash_hmac('sha256', $timestamp . '.' . $body, self::SECRET),
            $request->header('X-Tijaraq-Signature')[0],
        );
        $this->assertSame($payload['event_id'], $request->header('X-Tijaraq-Event-Id')[0]);
        $this->assertSame('ticket.replied', $request->header('X-Tijaraq-Event')[0]);

        $this->assertSame('ticket.replied', $payload['event']);
        $this->assertSame('company-wh', $payload['external_company_id']);
        $this->assertSame($this->identity1['user'], $payload['external_user_id']);
        $this->assertSame($ticket->id, $payload['ticket']['id']);
        $this->assertSame('agent', $payload['data']['message']['author_type']);
        $this->assertStringContainsString('Hello from support', $payload['data']['message']['body_html']);
        $this->assertNotEmpty($payload['occurred_at']);

        $this->assertSame(
            WebhookDelivery::DELIVERED,
            WebhookDelivery::where('event_id', $payload['event_id'])->value('status'),
        );
    }

    public function test_the_webhook_secret_is_never_part_of_the_payload_or_the_stored_row(): void
    {
        $this->agentReply($this->ticket());

        foreach (WebhookDelivery::all() as $delivery) {
            $this->assertStringNotContainsString(self::SECRET, json_encode($delivery->toArray()));
        }
        $this->assertStringNotContainsString(self::SECRET, $this->sent()[0]->body());
    }

    public function test_internal_notes_do_not_emit_anything(): void
    {
        $ticket = $this->ticket();
        $agent = $this->makeAgent();

        $note = (new CreateConversationMessage())->execute($ticket, [
            'author' => 'agent',
            'user_id' => $agent->id,
            'type' => 'note',
            'body' => 'internal only',
        ]);
        event(new ConversationMessageCreated($ticket, $note));

        Http::assertNothingSent();
        $this->assertSame(0, WebhookDelivery::count());
    }

    public function test_customer_messages_do_not_emit_anything(): void
    {
        $ticket = $this->ticket();

        $this->signed('POST', "tickets/{$ticket->id}/replies", [
            'identity' => $this->identity1,
            'json' => ['body_html' => '<p>customer follow up</p>'],
            'idempotency' => \App\Integrations\Tijaraq\Support\Uuid::v4(),
        ])->assertStatus(201);

        $this->assertCount(0, $this->sent('ticket.replied'));
    }

    public function test_status_change_emits_ticket_status_changed(): void
    {
        $ticket = $this->ticket();
        $pending = ConversationStatus::getDefaultPending();

        Conversation::changeStatus($pending, [$ticket]);

        $requests = $this->sent('ticket.status_changed');
        $this->assertCount(1, $requests);
        $payload = json_decode($requests[0]->body(), true);
        $this->assertSame(['from' => 'open', 'to' => 'pending'], $payload['data']);
        $this->assertSame('pending', $payload['ticket']['status']);
    }

    public function test_a_status_change_made_by_a_trigger_still_emits(): void
    {
        $ticket = $this->ticket();

        // while a triggers cycle runs the vendor listener returns false and
        // stops every LATER listener; ours is registered earlier
        TriggersCycle::$isRunning = true;
        try {
            Conversation::changeStatus(ConversationStatus::getDefaultClosed(), [$ticket]);
        } finally {
            TriggersCycle::$isRunning = false;
        }

        $requests = $this->sent('ticket.status_changed');
        $this->assertCount(1, $requests);
        $this->assertSame(
            ['from' => 'open', 'to' => 'closed'],
            json_decode($requests[0]->body(), true)['data'],
        );
    }

    public function test_changing_between_statuses_of_the_same_category_is_not_reported(): void
    {
        $ticket = $this->ticket();

        Conversation::changeStatus(ConversationStatus::getDefaultOpen(), [$ticket]);

        $this->assertCount(0, $this->sent('ticket.status_changed'));
    }

    public function test_assignment_emits_ticket_assigned_once(): void
    {
        $ticket = $this->ticket();
        $agent = $this->makeAgent('Dina');

        // fires both ConversationsUpdated and ConversationsAssignedToAgent
        ConversationsAssigner::assignConversationsToAgent([$ticket], $agent->id);

        $requests = $this->sent('ticket.assigned');
        $this->assertCount(1, $requests, 'both event paths must dedupe to one webhook');
        $this->assertSame(
            ['assignee_name' => 'Dina'],
            json_decode($requests[0]->body(), true)['data'],
        );
        $this->assertSame(
            1,
            WebhookDelivery::where('event', 'ticket.assigned')->count(),
        );
    }

    public function test_tickets_without_a_tenant_never_emit(): void
    {
        $agent = $this->makeAgent();
        $conversation = Conversation::create([
            'type' => 'ticket', 'subject' => 'Internal', 'status_id' => 1,
            'status_category' => 6, 'user_id' => $agent->id,
        ]);

        Conversation::changeStatus(ConversationStatus::getDefaultClosed(), [$conversation]);
        ConversationsAssigner::assignConversationsToAgent([$conversation], $agent->id);

        Http::assertNothingSent();
    }

    public function test_nothing_is_sent_when_the_webhook_is_not_configured(): void
    {
        config(['tijaraq-integration.webhook_url' => null]);

        Conversation::changeStatus(ConversationStatus::getDefaultClosed(), [$this->ticket()]);

        Http::assertNothingSent();
        $this->assertSame(0, WebhookDelivery::count());
    }

    public function test_a_failing_receiver_marks_the_delivery_failed_and_the_retry_command_requeues_it(): void
    {
        $this->receiverReturns(500);
        $ticket = $this->ticket();

        Conversation::changeStatus(ConversationStatus::getDefaultClosed(), [$ticket]);

        $delivery = WebhookDelivery::where('event', 'ticket.status_changed')->firstOrFail();
        $this->assertSame(WebhookDelivery::FAILED, $delivery->status);
        $this->assertSame(500, $delivery->last_status_code);
        $this->assertGreaterThanOrEqual(1, $delivery->attempts);

        $this->receiverReturns(204);
        $this->assertSame(0, Artisan::call('tijaraq:webhooks:retry', ['--failed' => true]));

        $delivery->refresh();
        $this->assertSame(WebhookDelivery::DELIVERED, $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertSame(204, $delivery->last_status_code);
    }

    public function test_retries_resend_the_same_event_id(): void
    {
        $this->receiverReturns(503);
        Conversation::changeStatus(ConversationStatus::getDefaultClosed(), [$this->ticket()]);
        $first = WebhookDelivery::firstOrFail();

        $this->receiverReturns(200);
        Artisan::call('tijaraq:webhooks:retry', ['--failed' => true]);

        $sent = collect($this->sent())->last();
        $this->assertSame($first->event_id, $sent->header('X-Tijaraq-Event-Id')[0]);
    }

    public function test_backoff_schedule_matches_the_contract(): void
    {
        $job = new \App\Integrations\Tijaraq\Jobs\DeliverWebhookJob(1);

        $this->assertSame([60, 300, 900, 3600, 10800], $job->backoff());
        $this->assertSame(6, $job->tries);
    }
}
