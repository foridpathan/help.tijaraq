<?php

namespace App\Integrations\Tijaraq\Actions;

use App\Conversations\Models\Conversation;
use App\Integrations\Tijaraq\Exceptions\IntegrationException;
use App\Integrations\Tijaraq\Services\CustomerProvisioner;
use App\Integrations\Tijaraq\Support\TijaraqContext;
use App\Models\User;
use Common\Files\FileEntry;
use Illuminate\Support\Facades\DB;

/**
 * Closes the attachment-substitution risk: an id is only accepted when the
 * file was uploaded through the integration by this company (by the caller
 * for scope=own) and is not already attached to any message.
 */
class ResolveAttachmentIds
{
    /** @return int[] */
    public function execute(TijaraqContext $ctx, array $ids): array
    {
        $ids = array_values(
            array_unique(array_map('intval', array_filter($ids, 'is_numeric'))),
        );

        if (empty($ids)) {
            return [];
        }

        $entries = FileEntry::query()
            ->whereIn('id', $ids)
            ->where('upload_type', Conversation::ATTACHMENT_UPLOAD_TYPE)
            ->get();

        if ($entries->count() !== count($ids)) {
            throw $this->invalid();
        }

        $owners = User::query()
            ->whereIn('id', $entries->pluck('owner_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        foreach ($entries as $entry) {
            $owner = $owners[$entry->owner_id] ?? null;
            $sameCompany =
                $owner &&
                $owner->external_source === CustomerProvisioner::SOURCE &&
                $owner->external_company_id === $ctx->tenant;
            $allowedOwner =
                $sameCompany &&
                ($ctx->isCompanyScope() || $owner->id === $ctx->user->id);

            if (!$allowedOwner) {
                throw $this->invalid();
            }
        }

        // rows with model_type "user" are only the ownership pivot
        $alreadyAttached = DB::table('file_entry_models')
            ->whereIn('file_entry_id', $ids)
            ->where('model_type', '!=', 'user')
            ->exists();
        if ($alreadyAttached) {
            throw $this->invalid();
        }

        return $ids;
    }

    protected function invalid(): IntegrationException
    {
        // same answer for missing, foreign and used ids: nothing to enumerate
        return IntegrationException::validation(
            'One or more attachments are invalid.',
            ['attachment_ids' => ['One or more attachments are invalid.']],
        );
    }
}
