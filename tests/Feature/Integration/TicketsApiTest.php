<?php

namespace Tests\Feature\Integration;

use App\Conversations\Models\Conversation;
use App\Conversations\Models\ConversationItem;
use App\Conversations\Models\ConversationStatus;
use App\Integrations\Tijaraq\Support\Uuid;

class TicketsApiTest extends IntegrationTestCase
{
    public function test_meta_returns_departments_categories_priorities_and_statuses(): void
    {
        $response = $this->signed('GET', 'meta')->assertOk();

        $this->assertNotEmpty($response->json('departments'));
        $this->assertContains('general', array_column($response->json('categories'), 'value'));
        $this->assertSame(
            ['low', 'medium', 'high', 'urgent'],
            array_column($response->json('priorities'), 'value'),
        );
        $this->assertSame(
            ['open', 'pending', 'closed', 'locked'],
            array_column($response->json('statuses'), 'key'),
        );
    }

    public function test_create_ticket_returns_the_contract_shape(): void
    {
        $response = $this->createTicket(
            ['tenant' => 'company-a', 'user' => 'u-100', 'name' => 'Rahim Uddin'],
            ['priority' => 'urgent'],
        )->assertStatus(201);

        $response->assertJsonStructure([
            'id', 'subject', 'status', 'status_label', 'priority', 'category',
            'department', 'assignee_name', 'requester' => ['external_user_id', 'name'],
            'last_reply_by', 'last_reply_at', 'created_at', 'updated_at',
        ]);
        $this->assertSame('open', $response->json('status'));
        $this->assertSame('urgent', $response->json('priority'));
        $this->assertSame('general', $response->json('category'));
        $this->assertSame('u-100', $response->json('requester.external_user_id'));
        $this->assertSame('Rahim Uddin', $response->json('requester.name'));
        $this->assertSame('customer', $response->json('last_reply_by'));

        $conversation = Conversation::find($response->json('id'));
        $this->assertSame('company-a', $conversation->external_company_id);
        $this->assertSame('ticket', $conversation->type);
        $this->assertSame(4, (int) $conversation->priority);
    }

    public function test_priority_maps_to_the_helpdesk_values(): void
    {
        foreach (['low' => 1, 'medium' => 2, 'high' => 3, 'urgent' => 4] as $key => $value) {
            $id = $this->createTicketId([], ['priority' => $key]);
            $this->assertSame($value, (int) Conversation::find($id)->priority);
            $this->signed('GET', "tickets/$id")->assertJsonPath('priority', $key);
        }
    }

    public function test_department_is_stored_and_returned(): void
    {
        $department = $this->signed('GET', 'meta')->json('departments.1');

        $response = $this->createTicket([], ['department_id' => $department['id']])
            ->assertStatus(201);

        $this->assertSame($department['id'], $response->json('department.id'));
        $this->assertSame($department['name'], $response->json('department.name'));
    }

    public function test_create_ticket_validation(): void
    {
        $this->signed('POST', 'tickets', [
            'json' => [
                'subject' => str_repeat('x', 192),
                'body_html' => '',
                'category' => 'does-not-exist',
                'priority' => 'critical',
                'department_id' => 999999,
                'attachment_ids' => range(1, 11),
            ],
            'idempotency' => Uuid::v4(),
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['code', 'message', 'details']]);
    }

    public function test_list_supports_status_search_and_pagination(): void
    {
        $identity = $this->uniqueIdentity();
        $a = $this->createTicketId($identity, ['subject' => 'Daraz sync broken']);
        $b = $this->createTicketId($identity, ['subject' => 'Invoice question']);
        $this->signed('POST', "tickets/$b/close", ['identity' => $identity])->assertOk();

        $open = $this->signed('GET', 'tickets', [
            'identity' => $identity,
            'query' => ['status' => 'open'],
        ])->assertOk();
        $this->assertSame([$a], array_column($open->json('data'), 'id'));

        $closed = $this->signed('GET', 'tickets', [
            'identity' => $identity,
            'query' => ['status' => 'closed'],
        ])->assertOk();
        $this->assertSame([$b], array_column($closed->json('data'), 'id'));

        $search = $this->signed('GET', 'tickets', [
            'identity' => $identity,
            'query' => ['search' => 'Daraz'],
        ])->assertOk();
        $this->assertSame([$a], array_column($search->json('data'), 'id'));

        $paged = $this->signed('GET', 'tickets', [
            'identity' => $identity,
            'query' => ['per_page' => 1, 'page' => 2],
        ])->assertOk();
        $this->assertSame(2, $paged->json('meta.current_page'));
        $this->assertSame(2, $paged->json('meta.last_page'));
        $this->assertSame(1, $paged->json('meta.per_page'));
        $this->assertSame(2, $paged->json('meta.total'));

        $this->signed('GET', 'tickets', [
            'identity' => $identity,
            'query' => ['per_page' => 51],
        ])->assertStatus(422);
    }

    public function test_messages_are_oldest_first_and_exclude_notes_and_events(): void
    {
        $identity = $this->uniqueIdentity();
        $id = $this->createTicketId($identity, ['body_html' => '<p>first</p>']);
        $conversation = Conversation::find($id);

        $agent = $this->makeAgent();
        $conversation->items()->create([
            'type' => 'note',
            'author' => 'agent',
            'user_id' => $agent->id,
            'body' => 'INTERNAL ONLY SECRET NOTE',
        ]);
        $conversation->items()->create([
            'type' => 'message',
            'author' => 'agent',
            'user_id' => $agent->id,
            'body' => '<p>agent reply</p>',
        ]);

        $response = $this->signed('GET', "tickets/$id/messages", ['identity' => $identity])
            ->assertOk();

        $messages = $response->json('data');
        $this->assertCount(2, $messages);
        $this->assertSame('customer', $messages[0]['author_type']);
        $this->assertSame('agent', $messages[1]['author_type']);
        $this->assertSame('Agent', $messages[1]['author_name']);
        $this->assertStringNotContainsString('INTERNAL ONLY', $response->getContent());
        $this->assertSame(
            ['id', 'author_type', 'author_name', 'body_html', 'attachments', 'created_at'],
            array_keys($messages[0]),
        );
        $this->assertSame(1, $response->json('meta.current_page'));

        $this->signed('GET', "tickets/$id", ['identity' => $identity])
            ->assertJsonPath('last_reply_by', 'agent');
    }

    public function test_reply_adds_a_customer_message_and_reopens_the_ticket(): void
    {
        $identity = $this->uniqueIdentity();
        $id = $this->createTicketId($identity);
        $this->signed('POST', "tickets/$id/close", ['identity' => $identity])
            ->assertOk()
            ->assertJsonPath('status', 'closed');

        $reply = $this->signed('POST', "tickets/$id/replies", [
            'identity' => $identity,
            'json' => ['body_html' => '<p>Still broken</p>'],
            'idempotency' => Uuid::v4(),
        ])->assertStatus(201);

        $this->assertSame('customer', $reply->json('author_type'));
        $this->assertSame($identity['name'], $reply->json('author_name'));
        $this->assertStringContainsString('Still broken', $reply->json('body_html'));

        $this->signed('GET', "tickets/$id", ['identity' => $identity])
            ->assertJsonPath('status', 'open');
    }

    public function test_close_and_reopen(): void
    {
        $identity = $this->uniqueIdentity();
        $id = $this->createTicketId($identity);

        $this->signed('POST', "tickets/$id/close", ['identity' => $identity])
            ->assertOk()
            ->assertJsonPath('status', 'closed');
        $this->signed('POST', "tickets/$id/reopen", ['identity' => $identity])
            ->assertOk()
            ->assertJsonPath('status', 'open');
    }

    public function test_locked_tickets_cannot_be_reopened_or_replied_to(): void
    {
        $identity = $this->uniqueIdentity();
        $id = $this->createTicketId($identity);
        $locked = ConversationStatus::where('category', Conversation::STATUS_LOCKED)->first();
        Conversation::changeStatus($locked, [Conversation::find($id)]);

        $this->signed('GET', "tickets/$id", ['identity' => $identity])
            ->assertJsonPath('status', 'locked');

        $this->signed('POST', "tickets/$id/reopen", ['identity' => $identity])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ticket_locked');

        $this->signed('POST', "tickets/$id/replies", [
            'identity' => $identity,
            'json' => ['body_html' => '<p>hello?</p>'],
            'idempotency' => Uuid::v4(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ticket_locked');
    }

    public function test_internal_notes_never_leak_through_any_endpoint(): void
    {
        $identity = $this->uniqueIdentity(['scope' => 'company']);
        $id = $this->createTicketId($identity);
        Conversation::find($id)->items()->create([
            'type' => ConversationItem::NOTE_TYPE,
            'author' => 'agent',
            'user_id' => $this->makeAgent()->id,
            'body' => 'NOTE-CANARY-12345',
        ]);

        foreach (["tickets/$id", "tickets/$id/messages", 'tickets'] as $path) {
            $this->assertStringNotContainsString(
                'NOTE-CANARY-12345',
                $this->signed('GET', $path, ['identity' => $identity])->getContent(),
            );
        }
    }

    public function test_agent_tools_cannot_be_reached_through_the_integration_identity(): void
    {
        $agent = $this->makeAgent();

        // an integration request that claims an agent's email never resolves
        // to that agent
        $this->signed('GET', 'tickets', [
            'identity' => $this->uniqueIdentity(['email' => $agent->email]),
        ])->assertOk();

        $agent->refresh();
        $this->assertNull($agent->external_source);
        $this->assertSame('agent', $agent->type);
    }
}
