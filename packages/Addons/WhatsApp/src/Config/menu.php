<?php

return [
    /**
     * Top level, next to Leads: this is the screen an agent lives in all
     * day, not something buried under Settings.
     */
    [
        'key' => 'whatsapp_inbox',
        'name' => 'whatsapp::app.inbox.title',
        'route' => 'admin.whatsapp.inbox.index',
        'sort' => 3,
        'icon-class' => 'icon-mail',
    ],

    [
        'key' => 'settings.other_settings.whatsapp',
        'name' => 'whatsapp::app.menu.title',
        'info' => 'whatsapp::app.menu.title-info',
        'route' => 'admin.settings.whatsapp.index',
        'sort' => 8,
        'icon-class' => 'icon-settings-webhooks',
    ],
];
