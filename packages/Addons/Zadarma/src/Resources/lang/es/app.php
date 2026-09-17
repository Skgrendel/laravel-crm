<?php

return [
    'menu' => [
        'title' => 'Zadarma VoIP',
        'title-info' => 'Gestiona la integración de Zadarma VoIP',
    ],

    'acl' => [
        'title' => 'Zadarma VoIP',
        'phone-title' => 'Softphone Zadarma',
    ],

    'phone' => [
        'button-title' => 'Abrir softphone',
        'page-title' => 'Softphone Zadarma',
        'loading' => 'Conectando…',
        'not-configured' => 'Zadarma no está habilitado o configurado todavía. Pide a un admin que lo configure en Settings.',
        'no-extension' => 'Todavía no tienes una extensión SIP asignada. Pide a un admin que te asigne una en Settings > Zadarma VoIP.',
        'key-error' => 'No se pudo iniciar el softphone: :error',
        'recent-calls-title' => 'Llamadas recientes',
        'no-recent-calls' => 'Todavía no hay llamadas registradas.',
        'no-lead-match' => 'Sin Lead asociado',
        'incoming' => 'Entrante',
        'outgoing' => 'Saliente',
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
            'extensions-title' => 'Mapeo de extensiones',
            'extensions-info' => 'Asocia la extensión SIP de Zadarma de cada vendedor con su usuario de Krayin, para que las actividades de llamada se atribuyan al agente correcto.',
            'save-mapping-btn' => 'Guardar mapeo',
            'extensions-update-success' => 'Mapeo de extensiones actualizado correctamente.',
            'duplicate-extension' => 'La extensión ":extension" está asignada a más de un agente.',
        ],
    ],
];
