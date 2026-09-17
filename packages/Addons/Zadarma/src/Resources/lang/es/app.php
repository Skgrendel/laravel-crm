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
            'webhook-url-title' => 'URL del webhook',
            'webhook-url-info' => 'Pega esta URL en la configuración de webhooks de la PBX de Zadarma para que los eventos de llamadas lleguen a este CRM. Zadarma firma cada petición y la validamos automáticamente de nuestro lado.',
            'copy-btn' => 'Copiar',
            'copy-success' => 'Copiado al portapapeles.',
            'regenerate-btn' => 'Regenerar',
            'webhook-url-regenerated' => 'URL del webhook regenerada. Recuerda actualizarla también del lado de Zadarma.',
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
