<?php

namespace Tests\Feature\Integration;

use App\Conversations\Email\Mailables\TicketReceivedMailable;
use App\Conversations\Models\Conversation;
use App\Conversations\Models\ConversationItem;
use App\Integrations\Tijaraq\Models\IdempotencyKey;
use App\Integrations\Tijaraq\Services\IdempotentExecutor;
use App\Integrations\Tijaraq\Support\Uuid;
use Common\Settings\Settings;
use Illuminate\Support\Facades\Mail;

class IdempotencyTest extends IntegrationTestCase
{
    public function test_replay_returns_the_original_ticket_without_a_second_row_or_email(): void
    {
        Mail::fake();
        app(Settings::class)->save([
            'tickets.send_ticket_created_notification' => '1',
        ]);

        $identity = $this->uniqueIdentity();
        $key = Uuid::v4();
        $payload = $this->ticketPayload();
        $before = Conversation::where('external_company_id', $identity['tenant'])->count();

        $first = $this->signed('POST', 'tickets', [
            'identity' => $identity,
            'json' => $payload,
            'idempotency' => $key,
        ])->assertStatus(201);
        $this->assertNull($first->headers->get(IdempotentExecutor::REPLAY_HEADER));

        $second = $this->signed('POST', 'tickets', [
            'identity' => $identity,
            'json' => $payload,
            'idempotency' => $key,
        ])->assertStatus(201);

        $this->assertSame('1', $second->headers->get(IdempotentExecutor::REPLAY_HEADER));
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame($first->json(), $second->json());

        $this->assertSame(
            $before + 1,
            Conversation::where('external_company_id', $identity['tenant'])->count(),
        );
        $this->assertSame(
            1,
            ConversationItem::where('conversation_id', $first->json('id'))
                ->where('type', 'message')
                ->count(),
        );
        // the mailable is queued, so look at queued + sent
        $this->assertSame(
            1,
            Mail::queued(TicketReceivedMailable::class)->count() +
                Mail::sent(TicketReceivedMailable::class)->count(),
        );
    }

    public function test_same_key_with_a_different_body_is_a_conflict(): void
    {
        $identity = $this->uniqueIdentity();
        $key = Uuid::v4();

        $this->signed('POST', 'tickets', [
            'identity' => $identity,
            'json' => $this->ticketPayload(),
            'idempotency' => $key,
        ])->assertStatus(201);

        $this->signed('POST', 'tickets', [
            'identity' => $identity,
            'json' => $this->ticketPayload(['subject' => 'Something else']),
            'idempotency' => $key,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'idempotency_conflict');
    }

    public function test_keys_are_scoped_per_tenant(): void
    {
        $key = Uuid::v4();
        $payload = $this->ticketPayload();

        $a = $this->signed('POST', 'tickets', [
            'identity' => $this->uniqueIdentity(['tenant' => 'company-a']),
            'json' => $payload,
            'idempotency' => $key,
        ])->assertStatus(201);
        $b = $this->signed('POST', 'tickets', [
            'identity' => $this->uniqueIdentity(['tenant' => 'company-b']),
            'json' => $payload,
            'idempotency' => $key,
        ])->assertStatus(201);

        $this->assertNotSame($a->json('id'), $b->json('id'));
    }

    public function test_idempotency_key_is_required_and_must_be_a_uuid(): void
    {
        $this->signed('POST', 'tickets', ['json' => $this->ticketPayload()])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->signed('POST', 'tickets', [
            'json' => $this->ticketPayload(),
            'idempotency' => 'not-a-uuid',
        ])->assertStatus(422);
    }

    public function test_replies_are_idempotent_too(): void
    {
        $identity = $this->uniqueIdentity();
        $id = $this->createTicketId($identity);
        $key = Uuid::v4();
        $body = ['body_html' => '<p>one reply only</p>'];

        $first = $this->signed('POST', "tickets/$id/replies", [
            'identity' => $identity, 'json' => $body, 'idempotency' => $key,
        ])->assertStatus(201);
        $second = $this->signed('POST', "tickets/$id/replies", [
            'identity' => $identity, 'json' => $body, 'idempotency' => $key,
        ])->assertStatus(201);

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(
            2,
            ConversationItem::where('conversation_id', $id)->where('type', 'message')->count(),
        );
    }

    public function test_failed_requests_do_not_consume_the_key(): void
    {
        $identity = $this->uniqueIdentity();
        $key = Uuid::v4();

        $this->signed('POST', 'tickets', [
            'identity' => $identity,
            'json' => $this->ticketPayload(['priority' => 'nope']),
            'idempotency' => $key,
        ])->assertStatus(422);

        $this->signed('POST', 'tickets', [
            'identity' => $identity,
            'json' => $this->ticketPayload(),
            'idempotency' => $key,
        ])->assertStatus(201);
    }

    public function test_expired_keys_are_forgotten_and_pruned(): void
    {
        $identity = $this->uniqueIdentity();
        $key = Uuid::v4();
        $payload = $this->ticketPayload();

        $first = $this->signed('POST', 'tickets', [
            'identity' => $identity, 'json' => $payload, 'idempotency' => $key,
        ])->assertStatus(201);

        IdempotencyKey::query()->update(['created_at' => now()->subHours(25)]);

        $second = $this->signed('POST', 'tickets', [
            'identity' => $identity, 'json' => $payload, 'idempotency' => $key,
        ])->assertStatus(201);
        $this->assertNotSame($first->json('id'), $second->json('id'));

        IdempotencyKey::query()->update(['created_at' => now()->subHours(30)]);
        $this->assertGreaterThan(0, IdempotentExecutor::prune());
        $this->assertSame(0, IdempotencyKey::count());
    }
}
