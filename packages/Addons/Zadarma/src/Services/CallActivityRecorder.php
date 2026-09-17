<?php

namespace Addons\Zadarma\Services;

use Addons\Zadarma\Repositories\ZadarmaCallLogRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\User\Models\UserProxy;

/**
 * Turns a finished Zadarma call (NOTIFY_END / NOTIFY_OUT_END) into a "call"
 * activity on the matching Lead, deduplicated by Zadarma's own call id.
 */
class CallActivityRecorder
{
    public function __construct(
        protected ZadarmaCallLogRepository $zadarmaCallLogRepository,
        protected ActivityRepository $activityRepository,
        protected PersonRepository $personRepository,
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

        $lead = $this->findLeadByPhone($contactNumber);

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
     * Find the most recently updated Lead belonging to a Person whose
     * contact numbers match the given phone number. Matching is done on
     * digits only so formatting differences (+, spaces, dashes, country
     * code variants) don't prevent a match.
     *
     * `contact_numbers` is a JSON array of free-form strings, so there's no
     * reliable way to normalize it purely in SQL. Instead, a raw LIKE on the
     * last 4 digits (almost never split by separators) narrows the
     * candidates cheaply, then the full digit-only comparison happens in
     * PHP to confirm the match.
     */
    protected function findLeadByPhone(?string $phone)
    {
        if (empty($phone)) {
            return null;
        }

        $digitsOnly = preg_replace('/\D+/', '', $phone);

        if (strlen($digitsOnly) < 6) {
            return null;
        }

        $significant = substr($digitsOnly, -10);
        $lastFour = substr($digitsOnly, -4);

        $candidates = $this->personRepository
            ->getModel()
            ->newQuery()
            ->whereNotNull('contact_numbers')
            ->where('contact_numbers', 'like', '%'.$lastFour.'%')
            ->orderByDesc('updated_at')
            ->get();

        $person = $candidates->first(function ($candidate) use ($significant) {
            foreach ($candidate->contact_numbers ?? [] as $contactNumber) {
                $candidateDigits = preg_replace('/\D+/', '', $contactNumber['value'] ?? '');

                if ($candidateDigits && str_ends_with($candidateDigits, $significant)) {
                    return true;
                }
            }

            return false;
        });

        if (! $person) {
            return null;
        }

        return $person->leads()->orderByDesc('updated_at')->first();
    }

    /**
     * Create the "call" activity and attach it to the lead. Since extension
     * → agent mapping doesn't exist yet (addon phase 1.4), the activity is
     * attributed to the lead's own owner, falling back to the first admin.
     */
    protected function createActivity(array $call, $lead)
    {
        $userModelClass = UserProxy::modelClass();

        $userId = $lead->user_id ?: $userModelClass::query()->value('id');

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
