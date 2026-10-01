<?php

return [
    'roles' => [
        [
            'name' => 'Customers',
            'default' => true,
            'internal' => true,
            'type' => 'users',
            'permissions' => [
                'articles.view',
                'conversations.create',
                'files.create',
            ],
        ],
        [
            'name' => 'Guests',
            'guests' => true,
            'internal' => true,
            'type' => 'users',
            'permissions' => [
                'articles.view',
                'files.create',
                'conversations.create',
            ],
        ],
        [
            'name' => 'Agents',
            'default' => false,
            'guests' => false,
            'type' => 'agents',
            'permission_type' => 'users',
            'permissions' => [
                'articles.view',
                'conversations.update',
                'canned_replies.update',
                'users.update',
                'articles.update',
                'campaigns.update',
                'tags.update',
                'files.create',
            ],
        ],
        [
            'name' => 'Admins',
            'default' => false,
            'guests' => false,
            'type' => 'agents',
            'permission_type' => 'users',
            'permissions' => [
                'admin.access',
                'appearance.update',
                'api.access',
                'articles.view',
                'conversations.update',
                'canned_replies.update',
                'users.update',
                'articles.update',
                'campaigns.update',
                'tags.update',
                'roles.update',
                'triggers.update',
                'views.update',
                'statuses.update',
                'localizations.update',
                'custom_pages.update',
                'files.update',
                'settings.update',
                'files.create',
            ],
        ],
    ],
    'all' => [
        'Customers' => [
            [
                'name' => 'articles.view',
                'display_name' => 'View help center',
                'description' =>
                    'Allow viewing of all help center articles and categories.',
            ],
            [
                'name' => 'conversations.create',
                'display_name' => 'Start conversations',
                'description' =>
                    'Allow creating new conversations via email, ticket portal, widget or other channels.',
            ],
            [
                'name' => 'files.create',
                'display_name' => 'Upload files',
                'description' =>
                    'Allow uploading attachments to conversations.',
            ],
        ],

        'Agent dashboard' => [
            [
                'name' => 'conversations.update',
                'display_name' => 'Manage conversations',
                'description' =>
                    'Allow full access to conversations in agent dashboard.',
            ],
            [
                'name' => 'articles.update',
                'display_name' => 'Manage help center',
                'description' =>
                    'Allow editing of all help center articles and categories.',
            ],
            [
                'name' => 'users.update',
                'display_name' => 'Manage customers',
                'description' => 'Allow customer management.',
            ],
            [
                'name' => 'canned_replies.update',
                'display_name' => 'Manage canned replies',
                'description' => 'Allow canned reply management.',
            ],
            [
                'name' => 'reports.view',
                'display_name' => 'View reports',
                'description' => 'Allow viewing reports.',
            ],
            [
                'name' => 'campaigns.update',
                'display_name' => 'Manage campaigns',
                'description' => 'Allow campaign management.',
            ],
            [
                'name' => 'ai_agent.update',
                'display_name' => 'Manage AI Agent',
                'description' =>
                    'Allow AI Agent management, including knowledge, settings, tools and flows.',
            ],
            [
                'name' => 'agents.update',
                'display_name' => 'Manage agents',
                'description' => 'Allow team management.',
            ],
        ],

        'REST API' => [
            [
                'name' => 'api.access',
                'display_name' => 'REST API',
                'description' => 'Allow usage of REST API',
            ],
        ],

        'Admin' => [
            [
                'name' => 'admin.access',
                'display_name' => 'Access admin area',
                'description' =>
                    'Required in order to access any admin area page.',
            ],
            [
                'name' => 'tags.update',
                'display_name' => 'Manage tags',
                'description' => 'Allow tag management from admin area.',
            ],
            [
                'name' => 'triggers.update',
                'display_name' => 'Manage triggers',
                'description' => 'Allow trigger management from admin area.',
            ],
            [
                'name' => 'views.update',
                'display_name' => 'Manage views',
                'description' => 'Allow view management from admin area.',
            ],
            [
                'name' => 'statuses.update',
                'display_name' => 'Manage statuses',
                'description' => 'Allow status management from admin area.',
            ],
            [
                'name' => 'attributes.update',
                'display_name' => 'Manage attributes',
                'description' => 'Allow attribute management from admin area.',
            ],
            [
                'name' => 'roles.update',
                'display_name' => 'Role management',
                'description' => 'Allow role management from admin area.',
            ],
            [
                'name' => 'localizations.update',
                'display_name' => 'Manage localizations',
                'description' =>
                    'Allow localization management from admin area.',
            ],
            [
                'name' => 'files.update',
                'display_name' => 'Manage files',
                'description' => 'Allow file management from admin area.',
            ],
            [
                'name' => 'custom_pages.update',
                'display_name' => 'Manage pages',
                'description' =>
                    'Allow custom page management from admin area.',
            ],
            [
                'name' => 'settings.update',
                'display_name' => 'Manage settings',
                'description' => 'Allow settings management from admin area.',
            ],
            [
                'name' => 'appearance.update',
                'display_name' => 'Appearance editor',
                'description' => 'Allows access to appearance editor.',
            ],
            [
                'name' => 'admin',
                'display_name' => 'Super admin',
                'description' => 'Gives full permissions.',
            ],
        ],
    ],
];
