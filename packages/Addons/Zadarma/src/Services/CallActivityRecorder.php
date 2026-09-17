<?php

namespace Addons\Zadarma\Services;

use Addons\Zadarma\Repositories\ZadarmaCallLogRepository;
use Addons\Zadarma\Repositories\ZadarmaExtensionMappingRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\User\Models\UserProxy;

/**
 * Turns a finished Zadarma call (NOTIFY_END / NOTIFY_OUT_END) into a "call"
 * activity on the matching Lead, deduplicated by Zadarma's own call id.
 */
class CallActivityRecorder
{
    public function __construct(
        protected ZadarmaCallLogRepository $zadarmaCallLogRepository,
        protected ZadarmaExtensionMappingRepository $zadarmaExtensionMappingRepository,
        protected ActivityRepository $activityRepository,
        protected PhoneLeadMatcher $phoneLeadMatcher,
    ) {}

    /**
     * @param  array{pbx_call_id:string,direction:string,caller_id:?string,called_number:?string,internal_extension:?string,duration:?int,disposition:?string,is_recorded:bool,call_id_with_rec:?string,call_start:?string}  $call
     */
    public function record(array $call): void
    {
        if ($this->zadarmaCallLogRepository->alreadyProcessed($call['pbx_call_id'])) {
            return;
        }

        /**
         * The "other side" of the call (i.e. not our PBX number) is what we
         * match against Lead contacts, regardless of call direction.
         */
        $contactNumber = $call['direction'] === 'incoming'
            ? $call['caller_id']
            : $call['called_number'];

        $lead = $this->phoneLeadMatcher->findLeadByPhone($contactNumber);

        $activity = $lead ? $this->createActivity($call, $lead) : null;

        if (! $lead) {
            Log::info('Zadarma call finished but no matching Lead was found.', [
                'pbx_call_id' => $call['pbx_call_id'],
                'direction'   => $call['direction'],
                'number'      => $contactNumber,
            ]);
        }

        $this->zadarmaCallLogRepository->create([
            'pbx_call_id'         => $call['pbx_call_id'],
            'direction'           => $call['direction'],
            'caller_id'           => $call['caller_id'],
            'called_number'       => $call['called_number'],
            'internal_extension'  => $call['internal_extension'],
            'duration'            => $call['duration'],
            'disposition'         => $call['disposition'],
            'is_recorded'         => $call['is_recorded'],
            'call_id_with_rec'    => $call['call_id_with_rec'],
            'lead_id'             => $lead?->id,
            'activity_id'         => $activity?->id,
        ]);
    }

    /**
     * Create the "call" activity and attach it to the lead. Attributed to
     * the agent mapped to the call's SIP extension when known, falling back
     * to the lead's own owner, then the first admin as a last resort.
     */
    protected function createActivity(array $call, $lead)
    {
        $userModelClass = UserProxy::modelClass();

        $userId = $this->zadarmaExtensionMappingRepository->findUserIdByExtension($call['internal_extension'])
            ?: $lead->user_id
            ?: $userModelClass::query()->value('id');

        $start = $call['call_start'] ? Carbon::parse($call['call_start']) : now();
        $end = $call['duration'] ? (clone $start)->addSeconds((int) $call['duration']) : $start;

        $directionLabel = $call['direction'] === 'incoming' ? 'Incoming call' : 'Outgoing call';

        $activity = $this->activityRepository->create([
            'type'          => 'call',
            'title'         => $directionLabel.' — '.($call['disposition'] ?? 'unknown'),
            'comment'       => $this->buildComment($call),
            'schedule_from' => $start,
            'schedule_to'   => $end,
            'is_done'       => 1,
            'user_id'       => $userId,
        ]);

        $lead->activities()->attach($activity->id);

        return $activity;
    }

    protected function buildComment(array $call): string
    {
        $lines = [
            'Direction: '.$call['direction'],
            'Duration: '.($call['duration'] ?? 0).'s',
            'Disposition: '.($call['disposition'] ?? 'unknown'),
        ];

        if ($call['is_recorded']) {
            $lines[] = 'Recording available in Zadarma (call id: '.$call['call_id_with_rec'].')';
        }

        return implode("\n", $lines);
    }
}
