<?php

use Addons\Zadarma\Models\ZadarmaCallLog;
use Addons\Zadarma\Repositories\ZadarmaExtensionMappingRepository;
use Addons\Zadarma\Services\CallActivityRecorder;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Source;

/**
 * A call from a number nobody has on file used to land in
 * `zadarma_call_logs` and nowhere else, so it never reached a pipeline —
 * while an equivalent WhatsApp message became a Lead. These pin the two
 * channels behaving the same way.
 */
function recordZadarmaCall(array $overrides = []): array
{
    $call = array_merge([
        'pbx_call_id' => 'test-call-'.uniqid(),
        'direction' => 'incoming',
        'caller_id' => '5730100'.rand(10000, 99999),
        'called_number' => '5730199'.rand(10000, 99999),
        'internal_extension' => null,
        'duration' => 42,
        'disposition' => 'answered',
        'is_recorded' => 0,
        'call_id_with_rec' => null,
        'call_start' => now()->toDateTimeString(),
    ], $overrides);

    app(CallActivityRecorder::class)->record($call);

    return $call;
}

function cleanUpCall(array $call): void
{
    $log = ZadarmaCallLog::where('pbx_call_id', $call['pbx_call_id'])->first();

    // Log first, then Lead, then Person: `leads.person_id` is ON DELETE
    // RESTRICT, so the Person cannot go until its Lead is gone.
    $personId = null;

    if ($log?->lead_id) {
        $lead = Lead::find($log->lead_id);
        $personId = $lead?->person_id;
    }

    $log?->delete();

    if ($personId) {
        Lead::where('person_id', $personId)->delete();
        Person::where('id', $personId)->delete();
    }
}

it('creates a lead from an incoming call from an unknown number', function () {
    $call = recordZadarmaCall(['direction' => 'incoming']);

    try {
        $log = ZadarmaCallLog::where('pbx_call_id', $call['pbx_call_id'])->first();

        expect($log)->not->toBeNull();
        expect($log->lead_id)->not->toBeNull();

        $lead = Lead::find($log->lead_id);

        expect($lead->title)->toContain($call['caller_id']);

        // Attributed to the phone source, not to whatever source happens to
        // be first in the table.
        expect(Source::find($lead->lead_source_id)->name)->toContain('Tel');

        // The call itself is still logged as an activity on the new Lead.
        expect($log->activity_id)->not->toBeNull();
    } finally {
        cleanUpCall($call);
    }
});

/**
 * An agent dialling a number that is not on file is prospecting, and that
 * belongs in the pipeline too — the contact number taken is the one dialled,
 * not the company's own line.
 */
it('creates a lead from an outgoing call, using the dialled number', function () {
    $call = recordZadarmaCall(['direction' => 'outgoing']);

    try {
        $log = ZadarmaCallLog::where('pbx_call_id', $call['pbx_call_id'])->first();

        $lead = Lead::find($log->lead_id);

        expect($lead->title)->toContain($call['called_number']);
        expect($lead->title)->not->toContain($call['caller_id']);
    } finally {
        cleanUpCall($call);
    }
});

/**
 * The agent whose extension handled the call owns the Lead: they just spoke
 * to this person, so handing the follow-up to a default owner would give it
 * to somebody with no context.
 */
it('gives the lead to the agent whose extension handled the call', function () {
    $admin = getDefaultAdmin();
    $repository = app(ZadarmaExtensionMappingRepository::class);

    $originalExtension = $repository->findExtensionByUserId($admin->id);
    $extension = 'calltest-'.rand(100, 999);

    $call = null;

    try {
        $repository->saveMappings([$admin->id => $extension]);

        $call = recordZadarmaCall(['internal_extension' => $extension]);

        $log = ZadarmaCallLog::where('pbx_call_id', $call['pbx_call_id'])->first();

        expect(Lead::find($log->lead_id)->user_id)->toBe($admin->id);
    } finally {
        if ($call) {
            cleanUpCall($call);
        }

        $repository->saveMappings([$admin->id => $originalExtension]);
    }
});

it('attaches the call to an existing lead instead of creating a duplicate', function () {
    $admin = getDefaultAdmin();

    // Randomised: `persons.unique_id` is unique per user+number, so a fixed
    // one collides with anything a previous run left behind.
    $phone = '5730155'.rand(10000, 99999);

    $existing = app(\Webkul\Lead\Repositories\LeadRepository::class)->create([
        'entity_type' => 'leads',
        'title' => 'Lead existente para prueba de llamada',
        'lead_value' => 0,
        'status' => 1,
        'user_id' => $admin->id,
        'lead_pipeline_id' => app(\Webkul\Lead\Repositories\PipelineRepository::class)->getDefaultPipeline()->id,
        'lead_pipeline_stage_id' => app(\Webkul\Lead\Repositories\PipelineRepository::class)->getDefaultPipeline()->stages()->first()->id,
        'lead_source_id' => Source::first()->id,
        'lead_type_id' => app(\Webkul\Lead\Repositories\TypeRepository::class)->first()->id,
        'person' => [
            'entity_type' => 'persons',
            'name' => 'Contacto existente '.$phone,
            'emails' => [],
            'contact_numbers' => [['label' => 'work', 'value' => $phone]],
            'user_id' => $admin->id,
        ],
    ]);

    $call = null;

    try {
        $call = recordZadarmaCall(['caller_id' => $phone]);

        $log = ZadarmaCallLog::where('pbx_call_id', $call['pbx_call_id'])->first();

        expect($log->lead_id)->toBe($existing->id);
    } finally {
        if ($call) {
            ZadarmaCallLog::where('pbx_call_id', $call['pbx_call_id'])->delete();
        }

        $personId = $existing->person_id;

        $existing->delete();
        Person::where('id', $personId)->delete();
    }
});
