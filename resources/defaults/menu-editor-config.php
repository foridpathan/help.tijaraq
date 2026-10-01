<?php

use App\Core\Modules;

return [
    'positions' => array_filter([
        [
            'name' => 'header',
            'label' => 'Landing page header',
            'route' => '/hc',
        ],
        [
            'name' => 'landing-page-footer',
            'label' => 'Landing page footer',
            'route' => '/hc',
        ],
        [
            'name' => 'dashboard-sidebar',
            'label' => 'Dashboard sidebar',
            'route' => '/dashboard',
        ],
    ]),
    'available_routes' => array_filter([
        '/hc',
        '/hc/tickets',
        '/hc/tickets/new',

        '/dashboard/conversations?viewId=mine',
        '/dashboard/hc/arrange',
        '/dashboard/hc/articles',
        ...Modules::livechatInstalled()
            ? ['/dashboard/ai-agents', '/admin/ai-agents']
            : [],
        '/dashboard/team',
        '/dashboard/team/{currentUser}/details',
        '/dashboard/team/groups',
        '/dashboard/team/invites',
        '/dashboard/customers',
        '/dashboard/views',
        '/dashboard/campaigns',
        '/dashboard/saved-replies',
        '/dashboard/settings',
        '/dashboard/reports',

        '/admin/hc/arrange',
        '/admin/hc/articles',
        '/admin/triggers',
        '/admin/reports',
        '/admin/attributes',
        '/admin/team/members',
        '/admin/customers',
        '/admin/campaigns',
        '/admin/views',
        '/admin/statuses',
    ]),
];
