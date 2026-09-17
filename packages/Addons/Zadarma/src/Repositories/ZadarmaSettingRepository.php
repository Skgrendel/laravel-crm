<?php

namespace Addons\Zadarma\Repositories;

use Illuminate\Support\Str;
use Webkul\Core\Eloquent\Repository;

class ZadarmaSettingRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Addons\Zadarma\Contracts\ZadarmaSetting';
    }

    /**
     * Retrieve the single Zadarma settings row, creating it with defaults
     * if it doesn't exist yet.
     *
     * `webhook_secret` is generated here (not typed by the admin) because
     * it's used as an unguessable path segment in the webhook URL, not as a
     * value Zadarma asks the admin to configure on their side.
     */
    public function getSettings()
    {
        $settings = $this->model->newQuery()->firstOrCreate([], [
            'enabled'        => false,
            'webhook_secret' => Str::random(40),
        ]);

        if (empty($settings->webhook_secret)) {
            $settings->update(['webhook_secret' => Str::random(40)]);
        }

        return $settings;
    }

    /**
     * Rotate the webhook URL secret, e.g. if it's suspected to have leaked.
     * The admin must update the URL configured on Zadarma's side afterwards.
     */
    public function regenerateWebhookSecret()
    {
        $settings = $this->getSettings();

        $settings->update(['webhook_secret' => Str::random(40)]);

        return $settings->fresh();
    }

    /**
     * Whether the Zadarma addon is enabled and has credentials configured.
     */
    public function isEnabled(): bool
    {
        $settings = $this->getSettings();

        return (bool) $settings->enabled
            && ! empty($settings->api_key)
            && ! empty($settings->api_secret);
    }
}
