<?php

return [
    'menu' => [
        'title' => 'Zadarma VoIP',
        'title-info' => 'Manage the Zadarma VoIP integration',
    ],

    'acl' => [
        'title' => 'Zadarma VoIP',
    ],

    'settings' => [
        'index' => [
            'title' => 'Zadarma VoIP',
            'enabled' => 'Enabled',
            'credentials-title' => 'Credentials',
            'api-key' => 'API Key',
            'api-secret' => 'API Secret',
            'webhook-secret' => 'Webhook Secret',
            'webhook-secret-info' => 'Used to validate that incoming call webhooks really come from Zadarma.',
            'test-connection-btn' => 'Test connection',
            'save-btn' => 'Save',
            'connected' => 'Connected',
            'disconnected' => 'Disconnected',
            'update-success' => 'Zadarma settings updated successfully.',
            'connection-success' => 'Connection successful.',
            'connection-failed' => 'Connection failed: :error',
            'missing-credentials' => 'Enter an API Key and API Secret first.',
        ],
    ],
];
