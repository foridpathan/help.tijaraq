<?php

return [
    'upload_types' => [
        'brandingImages' => [
            'visibility' => 'public',
            'label' => 'Branding images',
            'description' =>
                'All branding images uploaded by admin or agents. Including logos, campaigns and flows.',
            'defaults' => [
                'prefix' => 'branding-images',
                'accept' => ['image'],
                'max_file_size' => '3145728', //3mb
            ],
        ],
        'conversationAttachments' => [
            'visibility' => 'private',
            'dont_clean' => true,
            'label' => 'Conversation attachments',
            'description' => 'Files attached to conversation messages.',
            'defaults' => [
                'max_file_size' => '26214400', //25mb
            ],
        ],
        'articleAttachments' => [
            'visibility' => 'private',
            'label' => 'Article attachments',
            'description' => 'Files attached to help center articles.',
            'defaults' => [
                'max_file_size' => '5242880', //5mb
            ],
        ],
        'conversationImages' => [
            'visibility' => 'public',
            'label' => 'Conversation images',
            'description' => 'Inline images added to conversation messages.',
            'defaults' => [
                'prefix' => 'conversation-images',
                'accept' => ['image'],
                'max_file_size' => '2097152', //2mb
            ],
        ],
    ],
];
