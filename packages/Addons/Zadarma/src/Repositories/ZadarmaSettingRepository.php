<?php

namespace Addons\Zadarma\Repositories;

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
     */
    public function getSettings()
    {
        return $this->model->newQuery()->firstOrCreate([], [
            'enabled' => false,
        ]);
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
