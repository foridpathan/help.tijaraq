<?php

namespace App\Integrations\Tijaraq\Http\Requests;

class ListMessagesRequest extends IntegrationFormRequest
{
    public function rules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ];
    }
}
