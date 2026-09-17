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
            'admin.settings.zadarma.extensions.update',
            'admin.settings.zadarma.pbx.redirection.show',
            'admin.settings.zadarma.pbx.redirection.update',
            'admin.settings.zadarma.pbx.direct_numbers',
        ],
        'sort' => 3,
    ],

    /**
     * Separate top-level node (not nested under Settings) so a sales role
     * can be granted the softphone without also getting access to the
     * Zadarma credentials/settings screen.
     */
    [
        'key' => 'zadarma_phone',
        'name' => 'zadarma::app.acl.phone-title',
        'route' => [
            'admin.zadarma.phone.show',
            'admin.zadarma.phone.webrtc_key',
            'admin.zadarma.phone.lookup_lead',
        ],
        'sort' => 8,
    ],
];
