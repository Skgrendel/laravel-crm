<?php

return [
    [
        'key' => 'settings.other_settings.whatsapp',
        'name' => 'whatsapp::app.acl.title',
        'route' => [
            'admin.settings.whatsapp.index',
            'admin.settings.whatsapp.update',
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
        ],
        'sort' => 9,
    ],
];
