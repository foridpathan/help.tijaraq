<?php

namespace App\Integrations\Tijaraq\Http\Requests;

use App\Attributes\Models\CustomAttribute;
use App\Conversations\Models\Conversation;

/** Options of the ticket "category" dropdown attribute. */
class MetaOptions
{
    /** @return array<int, array{value:string,label:string}> */
    public function categories(): array
    {
        $attribute = CustomAttribute::query()
            ->where('key', 'category')
            ->where('type', Conversation::MODEL_TYPE)
            ->first();

        return collect($attribute?->config['options'] ?? [])
            ->map(
                fn($option) => [
                    'value' => (string) $option['value'],
                    'label' => (string) ($option['label'] ?? $option['value']),
                ],
            )
            ->values()
            ->all();
    }

    /** @return string[] */
    public function categoryValues(): array
    {
        return array_column($this->categories(), 'value');
    }
}
