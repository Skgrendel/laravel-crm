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
     * WhatsApp conversation. Requires `default_owner_id` configured on the
     * settings screen — there's no authenticated agent in this webhook
     * context to fall back to, and `leads.user_id` is required (not
     * nullable). Without it, the conversation/messages are still recorded;
     * the Lead link is simply backfilled once an admin configures it.
     */
    protected function createLead(string $phoneNumber, $person = null)
    {
        $settings = $this->whatsAppSettingRepository->getSettings();

        if (empty($settings->default_owner_id)) {
            Log::warning('WhatsApp lead capture: no default_owner_id configured, skipping Lead creation.', [
                'phone_number' => $phoneNumber,
            ]);

            return null;
        }

        $pipeline = $this->pipelineRepository->getDefaultPipeline();
        $stage = $pipeline->stages()->first();

        $source = $this->sourceRepository->findOneByField('name', 'WhatsApp')
            ?: $this->sourceRepository->first();

        $data = [
            'entity_type' => 'leads',
            'title' => "WhatsApp {$phoneNumber}",
            'lead_value' => 0,
            'status' => 1,
            'user_id' => $settings->default_owner_id,
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
                'user_id' => $settings->default_owner_id,
            ];

        return $this->leadRepository->create($data);
    }
}
