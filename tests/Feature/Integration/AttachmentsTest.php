<?php

namespace Tests\Feature\Integration;

use App\Conversations\Models\ConversationItem;
use App\Integrations\Tijaraq\Support\Uuid;
use Illuminate\Http\UploadedFile;

class AttachmentsTest extends IntegrationTestCase
{
    protected function upload(array $identity, ?UploadedFile $file = null)
    {
        return $this->signed('POST', 'attachments', [
            'identity' => $identity,
            'file' => $file ?? $this->fakeFile(),
        ]);
    }

    public function test_upload_returns_id_name_size_and_mime(): void
    {
        $this->upload($this->uniqueIdentity())
            ->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'size', 'mime'])
            ->assertJsonPath('name', 'invoice.pdf')
            ->assertJsonPath('mime', 'application/pdf');
    }

    public function test_the_helpdesk_allow_list_is_enforced(): void
    {
        $identity = $this->uniqueIdentity();

        $this->upload($identity, UploadedFile::fake()->create('shell.exe', 10, 'application/x-msdownload'))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->upload($identity, UploadedFile::fake()->create('big.pdf', 26 * 1024, 'application/pdf'))
            ->assertStatus(422);
    }

    public function test_the_upload_must_be_signed_over_the_file_bytes(): void
    {
        // signature computed for one file, a different file is sent
        $file = $this->fakeFile();
        $this->signed('POST', 'attachments', [
            'file' => $file,
            'mutate' => function (array $headers) {
                $headers['X-Tijaraq-Signature'] = str_repeat('a', 64);
                return $headers;
            },
        ])->assertStatus(401);
    }

    public function test_attachment_is_linked_to_the_ticket_message(): void
    {
        $identity = $this->uniqueIdentity();
        $attachmentId = $this->upload($identity)->json('id');

        $ticketId = $this->createTicketId($identity, ['attachment_ids' => [$attachmentId]]);

        $messages = $this->signed('GET', "tickets/$ticketId/messages", ['identity' => $identity])
            ->assertOk();
        $this->assertSame(
            [['id' => $attachmentId, 'name' => 'invoice.pdf', 'size' => 12288, 'mime' => 'application/pdf']],
            $messages->json('data.0.attachments'),
        );

        $download = $this->signed('GET', "attachments/$attachmentId", ['identity' => $identity])
            ->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));
    }

    public function test_attachment_ids_can_be_used_on_replies(): void
    {
        $identity = $this->uniqueIdentity();
        $ticketId = $this->createTicketId($identity);
        $attachmentId = $this->upload($identity)->json('id');

        $reply = $this->signed('POST', "tickets/$ticketId/replies", [
            'identity' => $identity,
            'json' => ['body_html' => '<p>see attached</p>', 'attachment_ids' => [$attachmentId]],
            'idempotency' => Uuid::v4(),
        ])->assertStatus(201);

        $this->assertSame($attachmentId, $reply->json('attachments.0.id'));
    }

    public function test_an_attachment_id_from_another_company_is_rejected(): void
    {
        $foreign = $this->upload($this->uniqueIdentity(['tenant' => 'company-b']))->json('id');

        $this->createTicket($this->uniqueIdentity(['tenant' => 'company-a']), [
            'attachment_ids' => [$foreign],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_an_attachment_of_a_coworker_is_rejected_in_own_scope_but_allowed_in_company_scope(): void
    {
        $coworker = $this->uniqueIdentity(['tenant' => 'company-a']);
        $id = $this->upload($coworker)->json('id');

        $this->createTicket($this->uniqueIdentity(['tenant' => 'company-a', 'scope' => 'own']), [
            'attachment_ids' => [$id],
        ])->assertStatus(422);

        $this->createTicket($this->uniqueIdentity(['tenant' => 'company-a', 'scope' => 'company']), [
            'attachment_ids' => [$id],
        ])->assertStatus(201);
    }

    public function test_an_attachment_already_attached_elsewhere_is_rejected(): void
    {
        $identity = $this->uniqueIdentity();
        $id = $this->upload($identity)->json('id');

        $this->createTicketId($identity, ['attachment_ids' => [$id]]);

        $this->createTicket($identity, ['attachment_ids' => [$id]])
            ->assertStatus(422);

        $ticket = $this->createTicketId($identity);
        $this->signed('POST', "tickets/$ticket/replies", [
            'identity' => $identity,
            'json' => ['body_html' => '<p>again</p>', 'attachment_ids' => [$id]],
            'idempotency' => Uuid::v4(),
        ])->assertStatus(422);
    }

    public function test_unknown_attachment_ids_are_rejected_with_the_same_error(): void
    {
        $this->createTicket([], ['attachment_ids' => [987654321]])
            ->assertStatus(422)
            ->assertJsonPath('error.details.attachment_ids.0', 'One or more attachments are invalid.');
    }

    public function test_attachments_of_internal_notes_are_not_downloadable(): void
    {
        $identity = $this->uniqueIdentity(['scope' => 'company']);
        $ticketId = $this->createTicketId($identity);
        $agent = $this->makeAgent();

        // an agent's file attached to an internal note
        $entry = \Common\Files\FileEntry::create([
            'name' => 'secret.pdf', 'file_name' => uniqid(), 'mime' => 'application/pdf',
            'file_size' => 10, 'type' => 'pdf', 'extension' => 'pdf', 'public' => false,
            'upload_type' => 'conversationAttachments', 'owner_id' => $agent->id,
            'backend_id' => 1,
        ]);
        $note = \App\Conversations\Models\Conversation::find($ticketId)->items()->create([
            'type' => ConversationItem::NOTE_TYPE, 'author' => 'agent',
            'user_id' => $agent->id, 'body' => 'note',
        ]);
        $note->attachments()->attach($entry->id);

        $this->signed('GET', "attachments/{$entry->id}", ['identity' => $identity])
            ->assertStatus(404);
    }

    public function test_every_mutating_call_and_download_is_audited(): void
    {
        $identity = $this->uniqueIdentity();
        $id = $this->upload($identity)->json('id');
        $ticket = $this->createTicketId($identity, ['attachment_ids' => [$id]]);
        $this->signed('GET', "attachments/$id", ['identity' => $identity])->assertOk();
        $this->signed('POST', "tickets/$ticket/close", ['identity' => $identity])->assertOk();

        $actions = \App\Integrations\Tijaraq\Models\AuditLog::query()
            ->where('external_user_id', $identity['user'])
            ->pluck('action')
            ->all();

        foreach (['attachment.upload', 'ticket.create', 'attachment.download', 'ticket.close'] as $expected) {
            $this->assertContains($expected, $actions);
        }
    }
}
