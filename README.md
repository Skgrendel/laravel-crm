# CRM — PRODERI / ACOFICUM

Krayin CRM con dos addons propios, reemplazando Bitrix24.

Este repo **no es Krayin a secas**: trae integraciones de WhatsApp y telefonía
construidas a medida, campos de ventas migrados de Bitrix24 y una API de
reportes que Krayin no incluye. Si venís de la documentación oficial de Krayin,
las diferencias están abajo.

---

## Qué hay acá

| | |
|---|---|
| **Base** | Krayin CRM (Laravel 12, PHP 8.3) |
| **Addon Zadarma** | Telefonía VoIP: softphone WebRTC, click-to-call desde el Lead, registro automático de llamadas, desvío de llamadas y DIDs |
| **Addon WhatsApp** | Captura pasiva de leads, panel de chat multiagente, envío de adjuntos, QR y estado de sesión en vivo |
| **Microservicio** | [`baileys-whatsapp-service`](../baileys-whatsapp-service) — repo aparte, Node, una sesión por equipo |
| **Campos de ventas** | 10 campos custom migrados de Bitrix24, creados por comando |
| **API de reportes** | Endpoints con Sanctum que exponen los leads **con** sus campos custom |

### Arquitectura

Son **dos equipos comerciales separados**, y por eso **dos instalaciones**
independientes de Krayin (bases distintas). No es una instancia con permisos
compartidos: el aislamiento entre los dos negocios es real, no una capa de
permisos que puede fallar.

```
PRODERI   → Krayin #1 ─┐
                       ├── microservicio Baileys (1 sesión por equipo)
ACOFICUM  → Krayin #2 ─┘        └── Zadarma (solo ACOFICUM)
```

El microservicio es **uno solo compartido**: cada sesión apunta al webhook de
su propia instalación, con su propio `apiKey` y `webhookSecret`.

---

## Requisitos

- PHP 8.3+, Composer
- MySQL 8+
- Node 20+ (para el microservicio de WhatsApp)

---

## Instalación local

```bash
composer install
cp .env.example .env     # ya trae timezone/locale correctos, revisar base de datos
php artisan krayin-crm:install
php artisan crm:install-sales-attributes
```

`krayin-crm:install` corre `migrate:fresh`, que **borra todas las tablas**. En
una base nueva está bien; sobre una con datos usar `php artisan migrate`.

Los addons no requieren pasos aparte: sus migraciones se cargan solas porque
los ServiceProviders están registrados en `bootstrap/providers.php`.

**Después de instalar**, configurar desde el panel:

- **Settings > Zadarma** — `api_key` / `api_secret` y el mapeo de extensiones
  por agente (solo ACOFICUM).
- **Settings > WhatsApp** — activar, `session_id` y `service_url`. El `api_key`
  y el `webhook_secret` se **autogeneran**: copiarlos al `config/sessions.json`
  del microservicio.
- **Settings > Attributes** — cargar las opciones de `Tipo de Afiliado` y
  `Pagado Por`. Se crean vacíos a propósito: son taxonomías del negocio y una
  lista inventada terminaría siendo la que la gente elige.

### Microservicio de WhatsApp

Vive en un repo separado. Ver su README para el detalle; en corto:

```bash
cd ../baileys-whatsapp-service
npm install --allow-git=all
cp config/sessions.example.json config/sessions.json   # completar con lo de Krayin
npm start
```

Para vincular un número: Settings > WhatsApp muestra el QR cuando la sesión está
esperando, y se escanea desde WhatsApp Business del celular del equipo.

---

## Comandos propios

| Comando | Para qué |
|---|---|
| `php artisan crm:install-sales-attributes` | Crea los 10 campos de ventas migrados de Bitrix24. Idempotente, hay que correrlo en **ambas** instalaciones. |
| `php artisan crm:api-token {email}` | Emite un token de Sanctum para la API de reportes. Se muestra una sola vez. |

> Los campos se crean por comando y no a mano porque el `code` de cada atributo
> es lo que identifica el campo en la API. Si diverge entre las dos
> instalaciones, cualquier reporte que cruce ambas se rompe en silencio.

---

## Documentación

| Doc | Contenido |
|---|---|
| [`CLAUDE.md`](CLAUDE.md) | Contexto del proyecto, decisiones de arquitectura y fases |
| [`docs/zadarma-addon.md`](docs/zadarma-addon.md) | Addon de telefonía: alcance, decisiones y limitaciones reales de la API de Zadarma |
| [`docs/whatsapp-addon.md`](docs/whatsapp-addon.md) | Addon de WhatsApp, incluido el registro de bugs encontrados en pruebas reales y la auditoría de seguridad |
| [`docs/reporting-api.md`](docs/reporting-api.md) | Campos custom y API de reportes |
| [`docs/deployment.md`](docs/deployment.md) | Checklist de puesta en producción |

---

## Tests

```bash
php artisan test
```

**Corren contra la base de desarrollo real** — este repo no usa
`RefreshDatabase`. Cualquier test que toque filas compartidas (settings,
usuarios, leads) tiene que capturar el valor original y restaurarlo en un
`finally`, o corrompe datos de verdad. Ya pasó tres veces; está documentado en
los docs de cada addon.

---

## Sobre Krayin

Basado en [Krayin CRM](https://krayincrm.com) (MIT). La documentación oficial
sirve para todo lo que es core; los addons de este repo no están ahí.
