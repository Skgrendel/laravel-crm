<?php

namespace Addons\WhatsApp\Services;

use Addons\WhatsApp\Repositories\WhatsAppConversationRepository;
use Addons\WhatsApp\Repositories\WhatsAppSettingRepository;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Repositories\TypeRepository;

/**
 * Passive lead capture (2.2): resolves a WhatsApp chat to a CRM Lead so a
 * Lead exists the moment a customer writes in, with no chat UI required.
 * The full multiagent chat panel (2.3) reads/writes on top of the same
 * conversation this creates.
 */
class WhatsAppLeadCreator
{
    public function __construct(
        protected WhatsAppConversationRepository $conversationRepository,
        protected WhatsAppSettingRepository $whatsAppSettingRepository,
        protected PersonRepository $personRepository,
        protected LeadRepository $leadRepository,
        protected PipelineRepository $pipelineRepository,
        protected SourceRepository $sourceRepository,
        protected TypeRepository $typeRepository,
    ) {}

    /**
     * Find or create the conversation for this chat, and — if it isn't
     * linked to a Lead yet — match it to an existing Person/Lead by phone
     * or create a new one.
     */
    public function resolveConversation(string $remoteJid, string $phoneNumber)
    {
        $conversation = $this->conversationRepository->findOrCreateByRemoteJid($remoteJid, $phoneNumber);

        if ($conversation->lead_id) {
            return $conversation;
        }

        $person = $this->findPersonByPhone($phoneNumber);

        $lead = $person
            ? $person->leads()->orderByDesc('updated_at')->first()
            : null;

        if (! $lead) {
            $lead = $this->createLead($phoneNumber, $person);
        }

        if ($lead) {
            $conversation->update([
                'person_id' => $lead->person_id,
                'lead_id' => $lead->id,
            ]);

            $conversation->refresh();
        }

        return $conversation;
    }

    /**
     * An agent claiming a conversation from the inbox's "unassigned" tab.
     *
     * A conversation ends up with no Lead when capture could not resolve an
     * owner (empty assignment pool, or the Lead was deleted afterwards). The
     * messages are all still there — this gives them a Lead again, owned by
     * whoever picked it up, so it stops being invisible to every scoped view.
     */
    public function claimForUser($conversation, int $userId)
    {
        if ($conversation->lead_id) {
            return null;
        }

        $person = $conversation->person_id
            ? $this->personRepository->find($conversation->person_id)
            : $this->findPersonByPhone($conversation->phone_number);

        $lead = $person
            ? $person->leads()->orderByDesc('updated_at')->first()
            : null;

        if (! $lead) {
            $lead = $this->createLead($conversation->phone_number, $person, $userId);
        }

        if (! $lead) {
            return null;
        }

        $conversation->update([
            'person_id' => $lead->person_id,
            'lead_id' => $lead->id,
        ]);

        return $lead;
    }

    /**
     * Same matching rule as the rest of the CRM's phone-based lookups
     * (Zadarma's PhoneLeadMatcher, Bryan's lead-unification criteria):
     * digits-only comparison against the last significant digits, since
     * `contact_numbers` is free-form text. `phoneNumber` here is already
     * digits-only — it's extracted straight from WhatsApp's JID.
     */
    protected function findPersonByPhone(string $phoneNumber)
    {
        $significant = substr($phoneNumber, -10);
        $lastFour = substr($phoneNumber, -4);

        $candidates = $this->personRepository
            ->getModel()
            ->newQuery()
            ->whereNotNull('contact_numbers')
            ->where('contact_numbers', 'like', '%'.$lastFour.'%')
            ->orderByDesc('updated_at')
            ->get();

        return $candidates->first(function ($candidate) use ($significant) {
            foreach ($candidate->contact_numbers ?? [] as $contactNumber) {
                $candidateDigits = preg_replace('/\D+/', '', $contactNumber['value'] ?? '');

                if ($candidateDigits && str_ends_with($candidateDigits, $significant)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Creates the Lead (and Person, if none matched) for a first-contact
     * WhatsApp conversation. The owner comes from `AgentAssigner` (fixed,
     * round-robin or least-loaded) — there's no authenticated agent in this
     * webhook context to fall back to, and `leads.user_id` is required (not
     * nullable). If no owner can be resolved at all, the conversation and
     * messages are still recorded; the Lead link is backfilled once an
     * admin configures assignment.
     */
    protected function createLead(string $phoneNumber, $person = null, ?int $forcedOwnerId = null)
    {
        $ownerId = $forcedOwnerId ?: app(AgentAssigner::class)->nextOwnerId();

        if (empty($ownerId)) {
            Log::warning('WhatsApp lead capture: no owner could be assigned, skipping Lead creation.', [
                'phone_number' => $phoneNumber,
            ]);

            return null;
        }

        $pipeline = $this->pipelineRepository->getDefaultPipeline();
        $stage = $pipeline->stages()->first();

        /**
         * Created when missing instead of falling back to `first()`: sources
         * are seeded in the installation's language, so there is no
         * "WhatsApp" row out of the box and the fallback quietly attributed
         * every WhatsApp lead to whatever came first — "Correo Electrónico"
         * here. Reports by source were wrong from the start because of it.
         */
        $source = $this->sourceRepository->findOneByField('name', 'WhatsApp')
            ?: $this->sourceRepository->create(['name' => 'WhatsApp']);

        $data = [
            'entity_type' => 'leads',
            'title' => "WhatsApp {$phoneNumber}",
            'lead_value' => 0,
            'status' => 1,
            'user_id' => $ownerId,
            'lead_pipeline_id' => $pipeline->id,
            'lead_pipeline_stage_id' => $stage->id,
            'lead_source_id' => $source->id,
            'lead_type_id' => $this->typeRepository->first()->id,
        ];

        $data['person'] = $person
            ? ['id' => $person->id]
            : [
                'entity_type' => 'persons',
                'name' => "WhatsApp {$phoneNumber}",
                'emails' => [],
                'contact_numbers' => [['label' => 'work', 'value' => $phoneNumber]],
                'user_id' => $ownerId,
            ];

        return $this->leadRepository->create($data);
    }
}
