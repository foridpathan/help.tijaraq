<?php

namespace App\Integrations\Tijaraq\Http\Requests;

use App\Integrations\Tijaraq\Support\TicketMaps;
use App\Team\Models\Group;
use Illuminate\Validation\Rule;

class CreateTicketRequest extends IntegrationFormRequest
{
    public function rules(): array
    {
        $categoryRules = ['required', 'string', 'max:64'];
        $options = app(MetaOptions::class)->categoryValues();
        if (!empty($options)) {
            $categoryRules[] = Rule::in($options);
        }

        return [
            'subject' => 'required|string|max:191',
            'body_html' => 'required|string|max:50000',
            'category' => $categoryRules,
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists((new Group())->getTable(), 'id'),
            ],
            'priority' => ['required', Rule::in(TicketMaps::PRIORITIES)],
            'attachment_ids' => 'nullable|array|max:10',
            'attachment_ids.*' => 'integer',
        ];
    }
}
