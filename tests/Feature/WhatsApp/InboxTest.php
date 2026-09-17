<?php

use Addons\WhatsApp\Models\WhatsAppConversation;
use Addons\WhatsApp\Repositories\WhatsAppConversationRepository;
use Addons\WhatsApp\Services\AgentAssigner;
use Webkul\Lead\Models\Lead;
use Webkul\User\Repositories\UserRepository;

beforeEach(function () {
    $this->originalSettings = captureWhatsAppSettings();

    whatsAppSettingsRepository()->getSettings()->update([
        'enabled' => true,
        'webhook_secret' => 'test-webhook-secret',
        'default_owner_id' => getDefaultAdmin()->id,
    ]);
});

afterEach(function () {
    whatsAppSettingsRepository()->getSettings()->update($this->originalSettings);
});

/**
 * The fixture is created through the real webhook, so it already carries an
 * inbound message timestamped "now". `recordMessage` only ever moves these
 * columns forward, so any test driving them with relative past times has to
 * start from a clean slate or its writes are correctly ignored.
 */
function resetInboxColumns(int $conversationId): void
{
    WhatsAppConversation::where('id', $conversationId)->update([
        'last_inbound_at' => null,
        'last_outbound_at' => null,
        'agent_last_read_at' => null,
        'first_response_seconds' => null,
    ]);
}

/**
 * The inbox is sorted by who has been waiting longest, so "waiting" has to
 * mean exactly one thing: the customer wrote last and nobody answered. An
 * `echo` counts as an answer — the agent replied from their phone — and
 * treating it otherwise would leave answered conversations sitting at the
 * top of the list, which is the fastest way to make agents stop trusting it.
 */
it('treats a reply from the phone as answered, not as waiting', function () {
    $conversation = createChatFixtureConversation();

    $repository = app(WhatsAppConversationRepository::class);

    try {
        resetInboxColumns($conversation->id);

        $repository->recordMessage($conversation->id, 'received', now()->subMinutes(10));

        expect(WhatsAppConversation::find($conversation->id)->isWaiting())->toBeTrue();

        $repository->recordMessage($conversation->id, 'echo', now()->subMinutes(2));

        expect(WhatsAppConversation::find($conversation->id)->isWaiting())->toBeFalse();
    } finally {
        deleteChatFixture($conversation);
    }
});

it('records the first response time once and never recomputes it', function () {
    $conversation = createChatFixtureConversation();

    $repository = app(WhatsAppConversationRepository::class);

    try {
        resetInboxColumns($conversation->id);

        $repository->recordMessage($conversation->id, 'received', now()->subMinutes(10));
        $repository->recordMessage($conversation->id, 'sent_api', now()->subMinutes(8));

        $first = WhatsAppConversation::find($conversation->id)->first_response_seconds;

        expect($first)->toBe(120);

        // A later exchange is not a "first response".
        $repository->recordMessage($conversation->id, 'received', now()->subMinutes(5));
        $repository->recordMessage($conversation->id, 'sent_api', now());

        expect(WhatsAppConversation::find($conversation->id)->first_response_seconds)->toBe($first);
    } finally {
        deleteChatFixture($conversation);
    }
});

it('lists waiting conversations before handled ones', function () {
    $admin = getDefaultAdmin();
    $waiting = createChatFixtureConversation();
    $handled = createChatFixtureConversation();

    $repository = app(WhatsAppConversationRepository::class);

    try {
        resetInboxColumns($waiting->id);
        resetInboxColumns($handled->id);

        $repository->recordMessage($waiting->id, 'received', now()->subHours(2));

        $repository->recordMessage($handled->id, 'received', now()->subMinutes(30));
        $repository->recordMessage($handled->id, 'sent_api', now()->subMinutes(1));

        $rows = collect(
            test()->actingAs($admin)
                ->getJson(route('admin.whatsapp.inbox.list', ['scope' => 'all']))
                ->assertOk()
                ->json('conversations')
        );

        $waitingPosition = $rows->search(fn ($row) => $row['id'] === $waiting->id);
        $handledPosition = $rows->search(fn ($row) => $row['id'] === $handled->id);

        expect($waitingPosition)->toBeLessThan($handledPosition);
        expect($rows[$waitingPosition]['is_waiting'])->toBeTrue();
        expect($rows[$handledPosition]['is_waiting'])->toBeFalse();
    } finally {
        deleteChatFixture($waiting);
        deleteChatFixture($handled);
    }
});

it('hides a colleague conversation from an agent scoped to their own records', function () {
    $admin = getDefaultAdmin();
    $conversation = createChatFixtureConversation();

    $otherUser = app(UserRepository::class)->create([
        'name'     => 'Inbox Scope Test Agent',
        'email'    => 'inbox-scope-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status'   => 1,
        'role_id'  => $admin->role_id,
    ]);

    $originalViewPermission = $admin->view_permission;

    try {
        Lead::where('id', $conversation->lead_id)->update(['user_id' => $otherUser->id]);
        $admin->update(['view_permission' => 'individual']);

        $ids = collect(
            test()->actingAs($admin)
                ->getJson(route('admin.whatsapp.inbox.list', ['scope' => 'all']))
                ->assertOk()
                ->json('conversations')
        )->pluck('id');

        expect($ids)->not->toContain($conversation->id);
    } finally {
        $admin->update(['view_permission' => $originalViewPermission]);
        Lead::where('id', $conversation->lead_id)->update(['user_id' => $admin->id]);
        $otherUser->delete();
        deleteChatFixture($conversation);
    }
});

/**
 * Round-robin has to survive an empty or stale pool: a Lead with no owner
 * vanishes from every scoped view at once, which is worse than one landing
 * on the wrong desk.
 */
it('falls back to the default owner when the assignment pool is unusable', function () {
    $admin = getDefaultAdmin();

    $inactive = app(UserRepository::class)->create([
        'name'     => 'Inactive Pool Agent',
        'email'    => 'inactive-pool-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status'   => 0,
        'role_id'  => $admin->role_id,
    ]);

    try {
        whatsAppSettingsRepository()->getSettings()->update([
            'assignment_mode' => 'round_robin',
            'assignment_user_ids' => [],
        ]);

        expect(app(AgentAssigner::class)->nextOwnerId())->toBe($admin->id);

        // A pool made only of deactivated agents is just as unusable.
        whatsAppSettingsRepository()->getSettings()->update([
            'assignment_user_ids' => [$inactive->id],
        ]);

        expect(app(AgentAssigner::class)->nextOwnerId())->toBe($admin->id);
    } finally {
        $inactive->delete();
    }
});

/**
 * A conversation with no Lead has messages nobody can reply to and is
 * invisible to every scoped view. Claiming is the only way out of that
 * state, so it has to work from the inbox in one click.
 */
it('lets an agent take an unassigned conversation and become its owner', function () {
    $admin = getDefaultAdmin();
    $conversation = createChatFixtureConversation();
    $originalLeadId = $conversation->lead_id;

    try {
        // Strip the Lead: this is exactly the state of a conversation whose
        // capture could not resolve an owner.
        Lead::where('id', $originalLeadId)->delete();
        WhatsAppConversation::where('id', $conversation->id)->update(['lead_id' => null]);

        $leadId = test()->actingAs($admin)
            ->postJson(route('admin.whatsapp.inbox.claim', $conversation->id))
            ->assertOk()
            ->json('lead_id');

        expect($leadId)->not->toBeNull();
        expect(Lead::find($leadId)->user_id)->toBe($admin->id);
        expect(WhatsAppConversation::find($conversation->id)->lead_id)->toBe($leadId);

        // Taking one that already has an owner is refused, not silently
        // reassigned.
        test()->actingAs($admin)
            ->postJson(route('admin.whatsapp.inbox.claim', $conversation->id))
            ->assertStatus(422);
    } finally {
        $fresh = WhatsAppConversation::find($conversation->id);

        if ($fresh?->lead_id) {
            Lead::where('id', $fresh->lead_id)->delete();
        }

        deleteChatFixture($conversation);
    }
});

it('rotates between the agents in the pool', function () {
    $admin = getDefaultAdmin();

    $second = app(UserRepository::class)->create([
        'name'     => 'Rotation Test Agent',
        'email'    => 'rotation-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status'   => 1,
        'role_id'  => $admin->role_id,
    ]);

    try {
        $pool = [$admin->id, $second->id];
        sort($pool);

        whatsAppSettingsRepository()->getSettings()->update([
            'assignment_mode' => 'round_robin',
            'assignment_user_ids' => $pool,
            'assignment_cursor' => 0,
        ]);

        $assigner = app(AgentAssigner::class);

        expect($assigner->nextOwnerId())->toBe($pool[0]);
        expect($assigner->nextOwnerId())->toBe($pool[1]);
        expect($assigner->nextOwnerId())->toBe($pool[0]);
    } finally {
        $second->delete();
    }
});
