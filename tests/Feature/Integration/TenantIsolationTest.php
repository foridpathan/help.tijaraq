<?php

namespace Tests\Feature\Integration;

use App\Integrations\Tijaraq\Support\Uuid;

/**
 * Company A must never see, change or download anything of company B, and
 * must not learn that it exists (always 404, never 403).
 */
class TenantIsolationTest extends IntegrationTestCase
{
    protected array $a1;
    protected array $a2;
    protected array $b1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a1 = $this->uniqueIdentity(['tenant' => 'company-a', 'scope' => 'own']);
        $this->a2 = $this->uniqueIdentity(['tenant' => 'company-a', 'scope' => 'own']);
        $this->b1 = $this->uniqueIdentity(['tenant' => 'company-b', 'scope' => 'company']);
    }

    public function test_other_company_cannot_list_show_or_read_messages(): void
    {
        $id = $this->createTicketId($this->a1);

        $list = $this->signed('GET', 'tickets', ['identity' => $this->b1])->assertOk();
        $this->assertNotContains($id, array_column($list->json('data'), 'id'));

        $this->signed('GET', "tickets/$id", ['identity' => $this->b1])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');
        $this->signed('GET', "tickets/$id/messages", ['identity' => $this->b1])
            ->assertStatus(404);
    }

    public function test_other_company_cannot_reply_close_or_reopen(): void
    {
        $id = $this->createTicketId($this->a1);

        $this->signed('POST', "tickets/$id/replies", [
            'identity' => $this->b1,
            'json' => ['body_html' => '<p>hijack</p>'],
            'idempotency' => Uuid::v4(),
        ])->assertStatus(404);
        $this->signed('POST', "tickets/$id/close", ['identity' => $this->b1])
            ->assertStatus(404);
        $this->signed('POST', "tickets/$id/reopen", ['identity' => $this->b1])
            ->assertStatus(404);

        // untouched
        $this->signed('GET', "tickets/$id", ['identity' => $this->a1])
            ->assertOk()
            ->assertJsonPath('status', 'open');
    }

    public function test_cross_tenant_response_is_identical_to_a_missing_ticket(): void
    {
        $id = $this->createTicketId($this->a1);

        $foreign = $this->signed('GET', "tickets/$id", ['identity' => $this->b1]);
        $missing = $this->signed('GET', 'tickets/99999999', ['identity' => $this->b1]);

        $this->assertSame($missing->status(), $foreign->status());
        $this->assertSame($missing->json(), $foreign->json());
    }

    public function test_own_scope_cannot_see_a_coworkers_ticket(): void
    {
        $id = $this->createTicketId($this->a1);

        $this->signed('GET', "tickets/$id", ['identity' => $this->a2])->assertStatus(404);
        $this->signed('GET', "tickets/$id/messages", ['identity' => $this->a2])->assertStatus(404);
        $this->signed('POST', "tickets/$id/close", ['identity' => $this->a2])->assertStatus(404);

        $list = $this->signed('GET', 'tickets', ['identity' => $this->a2])->assertOk();
        $this->assertNotContains($id, array_column($list->json('data'), 'id'));
    }

    public function test_company_scope_sees_every_ticket_of_the_company(): void
    {
        $id = $this->createTicketId($this->a1);
        $manager = $this->uniqueIdentity(['tenant' => 'company-a', 'scope' => 'company']);

        $this->signed('GET', "tickets/$id", ['identity' => $manager])->assertOk();
        $this->signed('GET', "tickets/$id/messages", ['identity' => $manager])->assertOk();

        $list = $this->signed('GET', 'tickets', ['identity' => $manager])->assertOk();
        $this->assertContains($id, array_column($list->json('data'), 'id'));

        // and still nothing of company B
        $this->createTicketId($this->b1);
        $ids = array_column(
            $this->signed('GET', 'tickets', ['identity' => $manager])->json('data'),
            'requester',
        );
        foreach ($ids as $requester) {
            $this->assertNotSame($this->b1['user'], $requester['external_user_id']);
        }
    }

    public function test_company_scope_user_can_reply_to_a_coworkers_ticket_as_themselves(): void
    {
        $id = $this->createTicketId($this->a1);
        $manager = $this->uniqueIdentity(['tenant' => 'company-a', 'scope' => 'company']);

        $reply = $this->signed('POST', "tickets/$id/replies", [
            'identity' => $manager,
            'json' => ['body_html' => '<p>Manager here</p>'],
            'idempotency' => Uuid::v4(),
        ])->assertStatus(201);

        $this->assertSame($manager['name'], $reply->json('author_name'));
    }

    public function test_tickets_created_by_agents_or_without_a_tenant_are_not_reachable(): void
    {
        $conversation = \App\Conversations\Models\Conversation::create([
            'type' => 'ticket',
            'subject' => 'Legacy ticket without tenant',
            'status_id' => 1,
            'status_category' => 6,
            'user_id' => $this->makeAgent()->id,
        ]);

        $this->signed('GET', "tickets/{$conversation->id}", [
            'identity' => $this->uniqueIdentity(['scope' => 'company']),
        ])->assertStatus(404);
    }

    public function test_attachment_download_is_scoped_to_the_tenant(): void
    {
        $upload = $this->signed('POST', 'attachments', [
            'identity' => $this->a1,
            'file' => $this->fakeFile(),
        ])->assertStatus(201);

        $ticket = $this->createTicketId($this->a1, [
            'attachment_ids' => [$upload->json('id')],
        ]);

        $this->signed('GET', "attachments/{$upload->json('id')}", ['identity' => $this->a1])
            ->assertOk();

        // coworker (own scope) and other company: 404
        $this->signed('GET', "attachments/{$upload->json('id')}", ['identity' => $this->a2])
            ->assertStatus(404);
        $this->signed('GET', "attachments/{$upload->json('id')}", ['identity' => $this->b1])
            ->assertStatus(404);

        // company scope of the same tenant: allowed
        $this->signed('GET', "attachments/{$upload->json('id')}", [
            'identity' => $this->uniqueIdentity(['tenant' => 'company-a', 'scope' => 'company']),
        ])->assertOk();

        $this->assertNotNull($ticket);
    }
}
