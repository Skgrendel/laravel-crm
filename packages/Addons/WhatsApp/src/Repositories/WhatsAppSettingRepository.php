<?php

namespace Addons\WhatsApp\Repositories;

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
     */
    public function getSettings()
    {
        return $this->model->newQuery()->firstOrCreate([], [
            'enabled' => false,
        ]);
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
