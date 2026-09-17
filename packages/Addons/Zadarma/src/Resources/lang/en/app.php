<?php

return [
    'menu' => [
        'title' => 'Zadarma VoIP',
        'title-info' => 'Manage the Zadarma VoIP integration',
    ],

    'acl' => [
        'title' => 'Zadarma VoIP',
        'phone-title' => 'Zadarma Softphone',
    ],

    'phone' => [
        'button-title' => 'Open softphone',
        'page-title' => 'Zadarma Softphone',
        'loading' => 'Connecting…',
        'not-configured' => 'Zadarma isn\'t enabled or configured yet. Ask an admin to set it up in Settings.',
        'no-extension' => 'No SIP extension is mapped to your user yet. Ask an admin to assign one in Settings > Zadarma VoIP.',
        'key-error' => 'Could not start the softphone: :error',
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
            'extensions-title' => 'Extension mapping',
            'extensions-info' => 'Match each agent\'s Zadarma SIP extension to their Krayin user, so call activities get attributed to the right agent.',
            'save-mapping-btn' => 'Save mapping',
            'extensions-update-success' => 'Extension mapping updated successfully.',
            'duplicate-extension' => 'Extension ":extension" is assigned to more than one agent.',
        ],
    ],
];
