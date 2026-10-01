<?php

use App\Conversations\Models\Conversation;
use App\Conversations\Models\ConversationItem;
use App\HelpCenter\Models\HcArticle;
use App\Models\User;
use App\Team\Models\Group;
use Common\Tags\Tag;

return [
    'meilisearch' => [
        'index-settings' => [
            HcArticle::class => [],
            User::class => [],
            Tag::class => [],
            Conversation::class => [
                'stopWords' => ['the', 'a', 'an'],
                'rankingRules' => [
                    'updated_at:desc',
                    'words',
                    'typo',
                    'proximity',
                    'attribute',
                    'exactness',
                ],
            ],
        ],
    ],
    'mysql' => [
        'index-settings' => [
            ConversationItem::class => [],
            Group::class => [],
        ],
    ],
];
