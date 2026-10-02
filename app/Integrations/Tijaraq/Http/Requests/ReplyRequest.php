<?php

namespace App\Integrations\Tijaraq\Http\Requests;

class ReplyRequest extends IntegrationFormRequest
{
    public function rules(): array
    {
        return [
            'body_html' => 'required|string|max:50000',
            'attachment_ids' => 'nullable|array|max:10',
            'attachment_ids.*' => 'integer',
        ];
    }
}
