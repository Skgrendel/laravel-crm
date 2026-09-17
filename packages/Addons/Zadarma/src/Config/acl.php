<?php

return [
    [
        'key' => 'settings.other_settings.zadarma',
        'name' => 'zadarma::app.acl.title',
        'route' => [
            'admin.settings.zadarma.index',
            'admin.settings.zadarma.update',
            'admin.settings.zadarma.webhook_secret.regenerate',
            'admin.settings.zadarma.test_connection',
        ],
        'sort' => 3,
    ],
];
