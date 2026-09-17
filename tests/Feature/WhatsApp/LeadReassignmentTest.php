<?php

use Addons\WhatsApp\Repositories\WhatsAppConversationRepository;
use Illuminate\Support\Facades\Event;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Repositories\TypeRepository;
use Webkul\User\Repositories\UserRepository;

/**
 * Fase 2.4 (docs/whatsapp-addon.md) asked for "selector de agente en el Lead,
 * log de actividad de reasignaciones". Turns out both already exist in
 * Krayin core with zero addon code:
 *  - the "selector de agente" is the Lead's existing `user_id` (Sales Owner)
 *    attribute, editable inline like any other Lead attribute;
 *  - `Webkul\Activity\Traits\LogsActivity` (used by `AttributeValue`) already
 *    logs a `system` activity with old/new value for ANY attribute change on
 *    ANY entity that has an `activities()` relation, including Sales Owner.
 * This test pins that core behavior for WhatsApp-linked leads specifically,
 * so a future Krayin upgrade that changes it doesn't silently invalidate
 * that claim in the docs. No `Addons\WhatsApp` code is exercised here.
 *
 * No RefreshDatabase in this project — every row created is torn down at
 * the end (see [[feedback-test-state-isolation]]), including a throwaway
 * second user since this dev database only seeds one admin.
 */
function createReassignmentTestLead(int $ownerId)
{
    $pipeline = app(PipelineRepository::class)->getDefaultPipeline();
    $stage = $pipeline->stages()->first();
    $source = app(SourceRepository::class)->first();
    $type = app(TypeRepository::class)->first();

    return app(LeadRepository::class)->create([
        'entity_type' => 'leads',
        'title' => 'Test lead for reassignment audit',
        'lead_value' => 0,
        'status' => 1,
        'user_id' => $ownerId,
        'lead_pipeline_id' => $pipeline->id,
        'lead_pipeline_stage_id' => $stage->id,
        'lead_source_id' => $source->id,
        'lead_type_id' => $type->id,
        'person' => [
            'entity_type' => 'persons',
            'name' => 'Test Person for WhatsApp reassignment',
            'emails' => [],
        ],
    ]);
}

function reassignTestLeadOwner($lead, int $newOwnerId)
{
    Event::dispatch('lead.update.before', $lead->id);

    $updated = app(LeadRepository::class)->update([
        'entity_type' => 'leads',
        'user_id' => $newOwnerId,
    ], $lead->id);

    Event::dispatch('lead.update.after', $updated);

    return $updated;
}

function createReassignmentTestSecondUser(int $roleId)
{
    return app(UserRepository::class)->create([
        'name' => 'Test Second Agent',
        'email' => 'test-second-agent-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'role_id' => $roleId,
    ]);
}

it('logs a core system activity with old/new owner when a WhatsApp-linked lead is reassigned', function () {
    $admin = getDefaultAdmin();
    $otherUser = createReassignmentTestSecondUser($admin->role_id);

    $lead = createReassignmentTestLead($admin->id);

    $conversation = app(WhatsAppConversationRepository::class)->create([
        'remote_jid' => 'reassign-test-'.uniqid().'@s.whatsapp.net',
        'phone_number' => '5491100000000',
        'lead_id' => $lead->id,
    ]);

    reassignTestLeadOwner($lead, $otherUser->id);

    $activity = app(ActivityRepository::class)
        ->getModel()
        ->newQuery()
        ->where('type', 'system')
        ->whereHas('leads', fn ($query) => $query->where('leads.id', $lead->id))
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);

    expect($additional['old']['label'])->toBe($admin->name);
    expect($additional['new']['label'])->toBe($otherUser->name);

    $lead->activities()->detach();
    $activity->delete();
    $conversation->delete();
    $person = $lead->person;
    $lead->delete();
    $person->delete();
    $otherUser->delete();
});
