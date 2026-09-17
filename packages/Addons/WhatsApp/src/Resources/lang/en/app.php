<?php

return [
    'menu' => [
        'title' => 'WhatsApp',
        'title-info' => 'Manage the WhatsApp integration',
    ],

    'acl' => [
        'title' => 'WhatsApp',
    ],

    'settings' => [
        'index' => [
            'title' => 'WhatsApp',
            'enabled' => 'Enabled',
            'connection-title' => 'Microservice connection',
            'connection-info' => 'Points this installation at its own session on the baileys-whatsapp-service microservice (one number per installation — PRODERI and ACOFICUM each have their own).',
            'session-id' => 'Session ID',
            'session-id-placeholder' => 'e.g. proderi or acoficum',
            'service-url' => 'Microservice URL',
            'api-key' => 'API Key',
            'webhook-title' => 'Webhook secret',
            'webhook-info' => 'Generated automatically — copy it into this session\'s "webhookSecret" in the microservice\'s config/sessions.json so it can sign events for this CRM.',
            'webhook-secret-hidden' => 'Hidden for security — copy it right after it\'s generated.',
            'lead-capture-title' => 'Lead capture',
            'lead-capture-info' => 'New leads created automatically from a first WhatsApp message are assigned to this agent until manually reassigned — required for passive lead capture to work.',
            'default-owner' => 'Default owner',
            'status-title' => 'Session status',
            'status-connected' => 'Connected (:number)',
            'status-disconnected' => 'Disconnected',
            'status-unknown' => 'No status yet',
            'save-btn' => 'Save',
            'update-success' => 'WhatsApp settings updated successfully.',
        ],
    ],
];
