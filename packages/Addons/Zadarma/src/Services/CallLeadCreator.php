<?php

namespace Addons\Zadarma\Services;

use Addons\Zadarma\Repositories\ZadarmaExtensionMappingRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Repositories\TypeRepository;
use Webkul\User\Models\UserProxy;

/**
 * Creates a Lead for a call from (or to) a number the CRM doesn't know yet.
 *
 * Until now Zadarma only ever *attached* calls to Leads that already
 * existed: a call from an unknown number was written to `zadarma_call_logs`
 * and nothing else, so it never reached anyone's pipeline. That left the two
 * channels inconsistent — a WhatsApp message from a stranger became a Lead,
 * an equivalent phone call did not.
 */
class CallLeadCreator
{
    public function __construct(
        protected PersonRepository $personRepository,
        protected LeadRepository $leadRepository,
        protected PipelineRepository $pipelineRepository,
        protected SourceRepository $sourceRepository,
        protected TypeRepository $typeRepository,
        protected ZadarmaExtensionMappingRepository $zadarmaExtensionMappingRepository,
    ) {}

    public function createFromCall(array $call, string $phoneNumber)
    {
        if (empty($phoneNumber)) {
            return null;
        }

        /**
         * The agent whose extension handled the call owns the Lead. They
         * just spoke to this person — routing it to a default owner instead
         * would hand the follow-up to somebody with no context.
         */
        $ownerId = $this->zadarmaExtensionMappingRepository
            ->findUserIdByExtension($call['internal_extension'] ?? null)
            ?: UserProxy::modelClass()::query()->value('id');

        if (! $ownerId) {
            return null;
        }

        $pipeline = $this->pipelineRepository->getDefaultPipeline();
        $stage = $pipeline->stages()->first();

        return $this->leadRepository->create([
            'entity_type' => 'leads',
            'title' => trans('zadarma::app.lead.title-from-call', ['number' => $phoneNumber]),
            'lead_value' => 0,
            'status' => 1,
            'user_id' => $ownerId,
            'lead_pipeline_id' => $pipeline->id,
            'lead_pipeline_stage_id' => $stage->id,
            'lead_source_id' => $this->sourceId(),
            'lead_type_id' => $this->typeRepository->first()->id,
            'person' => [
                'entity_type' => 'persons',
                'name' => trans('zadarma::app.lead.person-from-call', ['number' => $phoneNumber]),
                'emails' => [],
                'contact_numbers' => [['label' => 'work', 'value' => $phoneNumber]],
                'user_id' => $ownerId,
            ],
        ]);
    }

    /**
     * Source names are seeded in the installation's language, so matching a
     * single hardcoded string silently falls through to whatever source
     * happens to be first — which is how WhatsApp leads ended up attributed
     * to "Correo Electrónico". Creating it when absent keeps reports by
     * source honest.
     */
    protected function sourceId(): int
    {
        foreach (['Teléfono', 'Telefono', 'Phone'] as $name) {
            $source = $this->sourceRepository->findOneByField('name', $name);

            if ($source) {
                return $source->id;
            }
        }

        return $this->sourceRepository->create(['name' => 'Teléfono'])->id;
    }
}
