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

    /**
     * The most recent finished calls handled by a given SIP extension, for
     * the "recent calls" panel next to the softphone dial pad.
     */
    public function recentForExtension(string $extension, int $limit = 15)
    {
        return $this->model->newQuery()
            ->with('lead')
            ->where('internal_extension', $extension)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
