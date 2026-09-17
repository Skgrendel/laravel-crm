<?php

return [
    'menu' => [
        'title' => 'Zadarma VoIP',
        'title-info' => 'Gestiona la integración de Zadarma VoIP',
    ],

    'acl' => [
        'title' => 'Zadarma VoIP',
    ],

    'settings' => [
        'index' => [
            'title' => 'Zadarma VoIP',
            'enabled' => 'Habilitado',
            'credentials-title' => 'Credenciales',
            'api-key' => 'API Key',
            'api-secret' => 'API Secret',
            'webhook-secret' => 'Webhook Secret',
            'webhook-secret-info' => 'Se usa para validar que los webhooks de llamadas entrantes realmente vienen de Zadarma.',
            'test-connection-btn' => 'Probar conexión',
            'save-btn' => 'Guardar',
            'connected' => 'Conectado',
            'disconnected' => 'Desconectado',
            'update-success' => 'La configuración de Zadarma se actualizó correctamente.',
            'connection-success' => 'Conexión exitosa.',
            'connection-failed' => 'Conexión fallida: :error',
            'missing-credentials' => 'Ingresa un API Key y API Secret primero.',
        ],
    ],
];
