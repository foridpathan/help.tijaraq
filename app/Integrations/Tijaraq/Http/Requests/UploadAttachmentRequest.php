<?php

namespace App\Integrations\Tijaraq\Http\Requests;

class UploadAttachmentRequest extends IntegrationFormRequest
{
    public function rules(): array
    {
        // type and size are checked by the helpdesk upload validator
        return ['file' => 'required|file'];
    }
}
