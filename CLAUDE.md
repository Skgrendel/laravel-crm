# Contexto del proyecto — Migración Bitrix24 → Krayin + Addons WhatsApp/Zadarma

## Resumen del proyecto

Cliente migra de Bitrix24 a Krayin CRM (Laravel open source) por razones de costo.
Actualmente usan Whatcrm (integración WhatsApp por QR, NO oficial) y Zadarma (VoIP,
API oficial) conectados a Bitrix24. Hay que reconstruir ambas integraciones como
addons propios de Krayin, sin pagar licencias de terceros.

Documentación de detalle por addon:
- [`docs/zadarma-addon.md`](docs/zadarma-addon.md) — Fase 1
- [`docs/whatsapp-addon.md`](docs/whatsapp-addon.md) — Fase 2
- [`docs/reporting-api.md`](docs/reporting-api.md) — campos custom de ventas
  (migrados de Bitrix24) y la API de reportes que los expone

## Estructura organizacional (IMPORTANTE)

Son **2 equipos comerciales completamente separados**, cada uno con su propio
número de WhatsApp:

- **PRODERI** — no usa Zadarma
- **ACOFICUM** — sí usa Zadarma, 4 personas en el equipo de ventas

**Decisión de arquitectura: 2 instalaciones separadas de Krayin** (bases de datos
distintas), no una sola instancia con pipelines compartidos ni el addon Multi-Tenant
de pago de Webkul. Motivo: aislamiento real de datos entre dos negocios de
compliance/antifraude, sin depender de una capa de permisos custom que podría
fallar y filtrar datos entre clientes. Los reportes comerciales cruzados NO son
necesarios porque ya se extraen aparte vía webhook a un sistema externo.

El microservicio Baileys (WhatsApp) SÍ puede compartirse entre ambas instalaciones
usando sesiones multi-tenant (una sesión por número), cada una apuntando a su
propio webhook de Krayin. El package de código (WhatsApp y Zadarma) es el mismo,
instalado 2 veces con configuración distinta por instalación.

## Plan de fases (orden acordado)

### Fase 1 — Zadarma (primero, más simple, API oficial) — ver docs/zadarma-addon.md
Checkpoint: los 4 vendedores de ACOFICUM llamando desde el CRM con actividad
registrada correctamente, antes de tocar WhatsApp.

### Fase 2 — WhatsApp — ver docs/whatsapp-addon.md
Checkpoint intermedio: lead capture pasivo funcionando en producción (2.2) antes
de construir el panel de chat completo — divide el riesgo de estabilidad de
Baileys en dos entregas en vez de un solo big-bang.

## Referencias de Krayin relevantes (aplican a ambos addons)

- Devportal: https://devdocs.krayincrm.com/ — arquitectura modular, Concord
  contracts, Repository Pattern (Prettus L5), Custom Attributes (para campos
  como `whatsapp_number` sin migración), REST API con Sanctum, Swagger en
  `/api/admin/documentation`.
- Pipelines nativos (gratis, incluidos en open source): Settings > Pipelines,
  cada uno con sus propias etapas. Ojo con bug conocido: si ningún pipeline
  queda marcado "Is Default", la lista de Leads rompe con 404.
- Convención de package: cada addon vive en `packages/Addons/<Nombre>/`
  (namespace `Addons\<Nombre>`, ej. `Addons\Zadarma`, `Addons\WhatsApp`) con su
  propio ServiceProvider registrado en `bootstrap/providers.php`.

## Cómo usar este documento con Claude Code

Al empezar una sesión de trabajo, indica explícitamente en qué fase/addon estás
(ej: "hoy trabajamos la Fase 1.3 de Zadarma") para que se enfoque en el doc de
detalle correspondiente en vez de cargar todo el contexto de ambos addons.
Actualiza el checklist de fases en el doc correspondiente a medida que se
completen tareas reales, para que el contexto no quede desactualizado.
