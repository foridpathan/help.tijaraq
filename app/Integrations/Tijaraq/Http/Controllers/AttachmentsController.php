<?php

namespace App\Integrations\Tijaraq\Http\Controllers;

use App\Conversations\Models\Conversation;
use App\Conversations\Models\ConversationItem;
use App\Integrations\Tijaraq\Actions\StoreAttachment;
use App\Integrations\Tijaraq\Exceptions\IntegrationException;
use App\Integrations\Tijaraq\Http\Requests\UploadAttachmentRequest;
use App\Integrations\Tijaraq\Http\Resources\TicketPresenter;
use App\Integrations\Tijaraq\Repositories\IntegrationTicketRepository;
use App\Integrations\Tijaraq\Services\AuditLogger;
use App\Integrations\Tijaraq\Services\CustomerProvisioner;
use App\Integrations\Tijaraq\Support\TijaraqContext;
use App\Models\User;
use Common\Files\FileEntry;
use Common\Files\Response\FileResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class AttachmentsController extends Controller
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    // resolved lazily: the router instantiates controllers before the HMAC
    // middleware has bound the context
    protected function ctx(): TijaraqContext
    {
        return app(TijaraqContext::class);
    }

    protected function tickets(): IntegrationTicketRepository
    {
        return new IntegrationTicketRepository($this->ctx());
    }

    public function store(UploadAttachmentRequest $request): JsonResponse
    {
        $entry = (new StoreAttachment())->execute(
            $this->ctx(),
            $request->file('file'),
        );
        $this->audit->log('attachment.upload', $this->ctx(), $request, null, [
            'attachment_id' => $entry->id,
        ]);

        return response()->json(app(TicketPresenter::class)->attachment($entry), 201);
    }

    public function show(Request $request, string $id)
    {
        $entry = ctype_digit($id)
            ? FileEntry::query()
                ->where('upload_type', Conversation::ATTACHMENT_UPLOAD_TYPE)
                ->find((int) $id)
            : null;

        if (!$entry || !$this->canDownload($entry)) {
            throw IntegrationException::notFound();
        }

        $this->audit->log('attachment.download', $this->ctx(), $request, null, [
            'attachment_id' => $entry->id,
        ]);

        return app(FileResponseFactory::class)->create($entry, 'attachment');
    }

    // Visible when attached to a customer-visible message of a ticket the
    // caller may see. Unattached uploads are only visible to their uploader
    // (or the same company in company scope).
    protected function canDownload(FileEntry $entry): bool
    {
        $itemIds = DB::table('file_entry_models')
            ->where('file_entry_id', $entry->id)
            ->where('model_type', ConversationItem::MODEL_TYPE)
            ->where('relation_type', 'attachments')
            ->pluck('model_id');

        if ($itemIds->isNotEmpty()) {
            return ConversationItem::query()
                ->whereIn('id', $itemIds)
                ->where('type', 'message')
                ->whereIn(
                    'conversation_id',
                    $this->tickets()->query()->select('conversations.id'),
                )
                ->exists();
        }

        if (
            DB::table('file_entry_models')
                ->where('file_entry_id', $entry->id)
                ->where('model_type', '!=', 'user')
                ->exists()
        ) {
            // attached to something that is not a ticket message
            return false;
        }

        $owner = $entry->owner_id ? User::find($entry->owner_id) : null;
        if (
            !$owner ||
            $owner->external_source !== CustomerProvisioner::SOURCE ||
            $owner->external_company_id !== $this->ctx()->tenant
        ) {
            return false;
        }

        return $this->ctx()->isCompanyScope() || $owner->id === $this->ctx()->user->id;
    }
}
