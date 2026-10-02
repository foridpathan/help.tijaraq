<?php

namespace App\Integrations\Tijaraq\Http\Requests;

class ListTicketsRequest extends IntegrationFormRequest
{
    public function rules(): array
    {
        return [
            'status' => 'nullable|in:open,pending,closed,locked',
            'search' => 'nullable|string|max:191',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ];
    }
}
