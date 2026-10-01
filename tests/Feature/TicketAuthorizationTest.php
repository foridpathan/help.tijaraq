<?php

namespace Tests\Feature;

use App\Conversations\Models\Conversation;
use App\Models\User;
use Common\Auth\Actions\CreateUser;
use Common\Files\FileEntry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Customer A must never be able to read or modify customer B's tickets or
 * attachments (IDOR), and customers must not reach agent endpoints.
 */
class TicketAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $customerA;
    protected User $customerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerA = $this->makeCustomer('a');
        $this->customerB = $this->makeCustomer('b');
    }

    protected function makeCustomer(string $label): User
    {
        return (new CreateUser())->execute([
            'email' => "$label-" . uniqid() . '@example.test',
            'password' => 'Secret-12345',
            'name' => "Customer $label",
            'email_verified_at' => now(),
        ]);
    }

    protected function asUser(User $user): static
    {
        $this->actingAs($user, 'sanctum');

        // the API only accepts non-frontend callers that have the api.access permission
        return $this->withHeaders([
            'Referer' => config('app.url') . '/',
            'Origin' => config('app.url'),
        ]);
    }

    protected function createTicketAs(User $user, array $attachmentIds = []): int
    {
        $response = $this->asUser($user)->postJson(
            '/api/v1/helpdesk/customer/tickets',
            [
                'subject' => 'Private ticket of ' . $user->name,
                'message' => [
                    'body' => 'Confidential details',
                    'attachments' => $attachmentIds,
                ],
            ],
        );
        $response->assertSuccessful();

        return $response->json('conversation.id');
    }

    public function test_owner_can_view_own_ticket_and_messages(): void
    {
        $id = $this->createTicketAs($this->customerA);

        $this->asUser($this->customerA)
            ->getJson("/api/v1/helpdesk/customer/tickets/$id")
            ->assertOk();
        $this->asUser($this->customerA)
            ->getJson("/api/v1/helpdesk/customer/conversations/$id/messages")
            ->assertOk();
    }

    public function test_other_customer_cannot_view_ticket(): void
    {
        $id = $this->createTicketAs($this->customerA);

        $this->asUser($this->customerB)
            ->getJson("/api/v1/helpdesk/customer/tickets/$id")
            ->assertNotFound();
    }

    public function test_other_customer_cannot_read_messages(): void
    {
        $id = $this->createTicketAs($this->customerA);

        $this->asUser($this->customerB)
            ->getJson("/api/v1/helpdesk/customer/conversations/$id/messages")
            ->assertForbidden();
    }

    public function test_other_customer_cannot_reply_to_ticket(): void
    {
        $id = $this->createTicketAs($this->customerA);

        $this->asUser($this->customerB)
            ->postJson("/api/v1/helpdesk/customer/conversations/$id/messages", [
                'body' => 'injected by customer B',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('conversation_items', [
            'conversation_id' => $id,
            'body' => 'injected by customer B',
        ]);
    }

    public function test_other_customer_cannot_close_ticket(): void
    {
        $id = $this->createTicketAs($this->customerA);
        $before = Conversation::find($id)->status_id;

        $this->asUser($this->customerB)
            ->postJson("/api/v1/helpdesk/customer/conversations/$id/mark-as-solved")
            ->assertNotFound();

        $this->assertSame($before, Conversation::find($id)->status_id);
    }

    public function test_ticket_list_only_contains_own_tickets(): void
    {
        $this->createTicketAs($this->customerA);

        $response = $this->asUser($this->customerB)->getJson(
            '/api/v1/helpdesk/customer/tickets',
        );

        $response->assertOk();
        $this->assertStringNotContainsString(
            'Private ticket of Customer a',
            $response->getContent(),
        );
    }

    public function test_customer_cannot_use_agent_endpoints(): void
    {
        $id = $this->createTicketAs($this->customerA);

        foreach (
            [
                "/api/v1/helpdesk/agent/conversations/$id",
                '/api/v1/helpdesk/agent/conversations',
                '/api/v1/helpdesk/customers',
                '/api/v1/helpdesk/customers/' . $this->customerA->id,
            ]
            as $url
        ) {
            $status = $this->asUser($this->customerB)->getJson($url)->status();
            $this->assertContains(
                $status,
                [403, 404],
                "Customer B reached $url (HTTP $status)",
            );
        }
    }

    public function test_attachment_is_only_downloadable_by_ticket_owner(): void
    {
        $upload = $this->asUser($this->customerA)->postJson(
            '/api/v1/uploads',
            [
                'file' => UploadedFile::fake()->createWithContent(
                    'a-secret.txt',
                    'secret-of-A',
                ),
                'uploadType' => 'conversationAttachments',
            ],
        );
        $upload->assertSuccessful();
        $entry = FileEntry::findOrFail($upload->json('fileEntry.id'));

        $this->createTicketAs($this->customerA, [$entry->id]);

        $url = '/file-entries/download/' . $entry->hash;

        $this->asUser($this->customerA)->get($url)->assertOk();
        $this->asUser($this->customerB)->get($url)->assertForbidden();

        // the customer UI downloads attachments through the conversation policy
        $policyUrl = $url . '?policy=conversationFileEntry';
        $this->asUser($this->customerA)->get($policyUrl)->assertOk();
        $this->asUser($this->customerB)->get($policyUrl)->assertForbidden();

        auth()->forgetGuards();
        $this->app['auth']->forgetGuards();
        $guestStatus = $this->withHeaders([
            'Referer' => config('app.url') . '/',
        ])
            ->get($url)
            ->status();
        $this->assertContains($guestStatus, [401, 403, 302]);
    }
}
