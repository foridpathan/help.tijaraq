<?php

namespace App\Conversations\Agent\Actions;

use App\Attributes\Models\CustomAttribute;
use App\Conversations\Models\Conversation;
use App\Core\Modules;
use Common\Tags\Tag;
use Envato\Models\PurchaseCode;

class FullConversationLoader
{
    public function loadData(Conversation $conversation): array
    {
        $conversation->load(['assignee', 'group']);

        $attributes = $conversation
            ->allCustomAttributesWithValue()
            ->where('materialized', false)
            ->get()
            ->map(
                fn(CustomAttribute $attribute) => $attribute->toCompactArray(
                    'agent',
                ),
            );

        $user = $conversation
            ->user()
            ->first()
            ->load(['bans', 'secondaryEmail', 'tags']);

        if (settings('envato.enable')) {
            $envatoPurchaseCodes = $user
                ->purchaseCodes()
                ->get()
                ->map(function (PurchaseCode $code) {
                    $code->support_expired =
                        !$code->supported_until ||
                        $code->supported_until->lt(now());
                    return $code;
                });
        }

        $session = $user->latestUserSession;

        $tags = $conversation->tags()->get()->map(
            fn(Tag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ],
        );

        $user = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'country' => $user->country,
            'city' => $session?->city,
            'timezone' => $user->timezone,
            'tags' => $user->tags->pluck('name'),
            'banned_at' => $user->banned_at,
            'bans' => $user->bans->map(
                fn($ban) => [
                    'id' => $ban->id,
                    'comment' => $ban->comment,
                    'expired_at' => $ban->expired_at,
                ],
            ),
        ];

        $data = [
            'conversation' => $conversation,
            'user' => $user,
            'session' => $session
                ? [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'platform' => $session->platform,
                    'browser' => $session->browser,
                    'device' => $session->device,
                ]
                : null,
            'attributes' => $attributes,
            'envatoPurchaseCodes' => $envatoPurchaseCodes ?? [],
            'tags' => $tags,
        ];

        if (Modules::aiInstalled()) {
            $data['summary'] = $conversation->summary;
        }

        return $data;
    }
}
