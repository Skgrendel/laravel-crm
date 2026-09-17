<?php

namespace Addons\WhatsApp\Services;

use Addons\WhatsApp\Repositories\WhatsAppSettingRepository;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Models\Lead;

/**
 * Decides which agent a newly captured WhatsApp Lead belongs to (Fase 3.3).
 *
 * `default_owner_id` is always the fallback: the pool can be empty because
 * nobody was selected yet, or because everyone in it was deactivated, and a
 * Lead with no owner is worse than one on the wrong desk — it disappears
 * from every scoped view at once.
 */
class AgentAssigner
{
    public function __construct(
        protected WhatsAppSettingRepository $whatsAppSettingRepository,
    ) {}

    public function nextOwnerId(): ?int
    {
        $settings = $this->whatsAppSettingRepository->getSettings();

        $pool = $this->activePool($settings);

        if (! $pool) {
            return $settings->default_owner_id;
        }

        return match ($settings->assignment_mode) {
            'round_robin' => $this->roundRobin($settings, $pool),
            'least_loaded' => $this->leastLoaded($pool),
            default => $settings->default_owner_id,
        };
    }

    /**
     * Inactive users are dropped rather than skipped over, so somebody on
     * holiday does not silently collect half the leads.
     */
    protected function activePool($settings): array
    {
        $ids = $settings->assignment_user_ids ?: [];

        if (! $ids) {
            return [];
        }

        return DB::table('users')
            ->whereIn('id', $ids)
            ->where('status', 1)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    protected function roundRobin($settings, array $pool): int
    {
        $cursor = $settings->assignment_cursor % count($pool);

        $settings->update(['assignment_cursor' => $cursor + 1]);

        return $pool[$cursor];
    }

    /**
     * Counts open Leads, not all of them: an agent who closed two hundred
     * deals is not "loaded", and counting history would freeze them out of
     * the rotation for good.
     */
    protected function leastLoaded(array $pool): int
    {
        $counts = Lead::query()
            ->whereIn('user_id', $pool)
            ->where('status', 1)
            ->groupBy('user_id')
            ->selectRaw('user_id, count(*) as total')
            ->pluck('total', 'user_id')
            ->all();

        $loads = [];

        foreach ($pool as $userId) {
            $loads[$userId] = $counts[$userId] ?? 0;
        }

        asort($loads);

        return (int) array_key_first($loads);
    }
}
