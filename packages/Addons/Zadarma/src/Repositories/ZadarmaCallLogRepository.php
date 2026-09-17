<?php

namespace Addons\Zadarma\Repositories;

use Webkul\Core\Eloquent\Repository;

class ZadarmaCallLogRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Addons\Zadarma\Contracts\ZadarmaCallLog';
    }

    /**
     * Whether this Zadarma call has already been processed. Webhook
     * deliveries can be retried by Zadarma, so this is checked before
     * creating an activity for a call.
     */
    public function alreadyProcessed(string $pbxCallId): bool
    {
        return $this->model->newQuery()->where('pbx_call_id', $pbxCallId)->exists();
    }
}
