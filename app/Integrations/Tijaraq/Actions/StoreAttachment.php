<?php

namespace App\Integrations\Tijaraq\Actions;

use App\Conversations\Models\Conversation;
use App\Integrations\Tijaraq\Exceptions\IntegrationException;
use App\Integrations\Tijaraq\Support\TijaraqContext;
use Common\Files\Actions\CreateFileEntry;
use Common\Files\Actions\FileUploadValidator;
use Common\Files\Actions\StoreFile;
use Common\Files\FileEntry;
use Common\Files\FileEntryPayload;
use Illuminate\Http\UploadedFile;

/**
 * Stores an upload as a private "conversationAttachments" FileEntry owned by
 * the provisioned user. Reuses the vendor allow-list and size validation.
 */
class StoreAttachment
{
    public function execute(TijaraqContext $ctx, UploadedFile $file): FileEntry
    {
        if (!$file->isValid()) {
            throw IntegrationException::validation('The file upload failed.', [
                'file' => ['The file upload failed.'],
            ]);
        }

        $payload = new FileEntryPayload([
            'file' => $file,
            'uploadType' => Conversation::ATTACHMENT_UPLOAD_TYPE,
            'ownerId' => $ctx->user->id,
        ]);

        $errors = FileUploadValidator::validateForUploadType(
            $payload->uploadType,
            $payload->size,
            $payload->clientExtension,
            $payload->clientMime,
        );
        if ($errors) {
            throw IntegrationException::validation($errors->first(), [
                'file' => [$errors->first()],
            ]);
        }

        (new StoreFile())->execute($payload, ['file' => $file]);

        return (new CreateFileEntry())->execute($payload);
    }
}
