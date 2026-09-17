<?php

return [
    'menu' => [
        'title' => 'WhatsApp',
        'title-info' => 'Gestionar la integración de WhatsApp',
    ],

    'acl' => [
        'title' => 'WhatsApp',
    ],

    'settings' => [
        'index' => [
            'title' => 'WhatsApp',
            'enabled' => 'Habilitado',
            'connection-title' => 'Conexión con el microservicio',
            'connection-info' => 'Apunta esta instalación a su propia sesión en el microservicio baileys-whatsapp-service (un número por instalación — PRODERI y ACOFICUM tienen cada uno el suyo).',
            'session-id' => 'ID de sesión',
            'session-id-placeholder' => 'ej. proderi o acoficum',
            'service-url' => 'URL del microservicio',
            'api-key' => 'API Key',
            'webhook-title' => 'Secreto del webhook',
            'webhook-info' => 'Generado automáticamente — copialo en el "webhookSecret" de esta sesión dentro de config/sessions.json del microservicio para que pueda firmar los eventos hacia este CRM.',
            'webhook-secret-hidden' => 'Oculto por seguridad — copialo apenas se genera.',
            'lead-capture-title' => 'Captura de leads',
            'lead-capture-info' => 'Los leads nuevos creados automáticamente desde un primer mensaje de WhatsApp se asignan a este agente hasta que se reasignen manualmente — requerido para que la captura pasiva de leads funcione.',
            'default-owner' => 'Dueño por defecto',
            'status-title' => 'Estado de la sesión',
            'status-connected' => 'Conectado (:number)',
            'status-disconnected' => 'Desconectado',
            'status-unknown' => 'Todavía sin estado',
            'save-btn' => 'Guardar',
            'update-success' => 'Configuración de WhatsApp actualizada correctamente.',
        ],
    ],
];
