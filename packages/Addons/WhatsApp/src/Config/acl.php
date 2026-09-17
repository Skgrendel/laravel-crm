<?php

return [
    [
        'key' => 'settings.other_settings.whatsapp',
        'name' => 'whatsapp::app.acl.title',
        'route' => [
            'admin.settings.whatsapp.index',
            'admin.settings.whatsapp.update',
            'admin.settings.whatsapp.api_key.regenerate',
            'admin.settings.whatsapp.webhook_secret.regenerate',
            'admin.settings.whatsapp.session.status',
            'admin.settings.whatsapp.session.qr',
            'admin.settings.whatsapp.session.reconnect',
        ],
        'sort' => 4,
    ],

    /**
     * Separate top-level node (not nested under Settings) so a sales role
     * can use the chat panel without also getting access to the WhatsApp
     * credentials/settings screen — same reasoning as Zadarma's
     * `zadarma_phone` node.
     */
    [
        'key' => 'whatsapp_chat',
        'name' => 'whatsapp::app.acl.chat-title',
        'route' => [
            'admin.whatsapp.messages.index',
            'admin.whatsapp.messages.store',
            'admin.whatsapp.messages.media',
        ],
        'sort' => 9,
    ],

    [
        'key' => 'whatsapp_inbox',
        'name' => 'whatsapp::app.acl.inbox-title',
        'route' => [
            'admin.whatsapp.inbox.index',
            'admin.whatsapp.inbox.list',
            'admin.whatsapp.inbox.read',
            'admin.whatsapp.inbox.claim',
        ],
        'sort' => 10,
    ],
];
