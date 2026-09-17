<?php

namespace Addons\WhatsApp\Repositories;

use Illuminate\Support\Str;
use Webkul\Core\Eloquent\Repository;

class WhatsAppSettingRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Addons\WhatsApp\Contracts\WhatsAppSetting';
    }

    /**
     * Retrieve the single WhatsApp settings row, creating it with defaults
     * if it doesn't exist yet.
     *
     * `api_key` and `webhook_secret` are generated here — not typed by the
     * admin — because they're shared secrets *we* control on both ends
     * (this CRM and the baileys-whatsapp-service microservice), unlike
     * Zadarma's api_key/api_secret which a third party issues. The admin's
     * job is just to copy each one into the matching side once.
     */
    public function getSettings()
    {
        $settings = $this->model->newQuery()->firstOrCreate([], [
            'enabled' => false,
        ]);

        $missing = array_filter([
            'api_key'        => empty($settings->api_key) ? Str::random(40) : null,
            'webhook_secret' => empty($settings->webhook_secret) ? Str::random(40) : null,
        ]);

        if ($missing) {
            $settings->update($missing);
        }

        return $settings;
    }

    /**
     * Rotate the API key Krayin sends as `X-Api-Key` when calling the
     * microservice. The admin must update `apiKey` for this session in
     * `config/sessions.json` on the microservice side too.
     */
    public function regenerateApiKey()
    {
        $settings = $this->getSettings();

        $settings->update(['api_key' => Str::random(40)]);

        return $settings->fresh();
    }

    /**
     * Rotate the webhook signing secret the microservice uses to sign
     * events pushed to this CRM. The admin must update `webhookSecret` for
     * this session in `config/sessions.json` on the microservice side too.
     */
    public function regenerateWebhookSecret()
    {
        $settings = $this->getSettings();

        $settings->update(['webhook_secret' => Str::random(40)]);

        return $settings->fresh();
    }

    /**
     * Whether the WhatsApp addon is enabled and fully configured to receive
     * webhooks from the microservice.
     */
    public function isEnabled(): bool
    {
        $settings = $this->getSettings();

        return (bool) $settings->enabled
            && ! empty($settings->webhook_secret);
    }

    /**
     * Record the microservice's own view of the session's connection state,
     * pushed via the `session.connected` / `session.disconnected` webhook
     * events. Used by the settings screen's status card (2.5).
     */
    public function updateConnectionStatus(string $status, ?string $number = null): void
    {
        $settings = $this->getSettings();

        $settings->update([
            'last_status' => $status,
            'connected_number' => $number,
        ]);
    }
}
