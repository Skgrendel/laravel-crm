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
            'webhook-url-title' => 'Webhook URL',
            'webhook-url-info' => 'Paste this URL into Zadarma\'s PBX webhook settings so call events reach this CRM. Zadarma itself signs every request, verified automatically on our side.',
            'copy-btn' => 'Copy',
            'copy-success' => 'Copied to clipboard.',
            'regenerate-btn' => 'Regenerate',
            'webhook-url-regenerated' => 'Webhook URL regenerated. Remember to update it on Zadarma\'s side too.',
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
