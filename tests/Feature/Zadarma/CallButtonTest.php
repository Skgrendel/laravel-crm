<?php

use Addons\Zadarma\Repositories\ZadarmaExtensionMappingRepository;
use Webkul\Core\ViewRenderEventManager;

/**
 * Exercises the `admin.leads.view.actions.after` listener directly (rather
 * than through a real Lead page) since this dev database has no Lead
 * fixtures to safely test against — see [[feedback-test-state-isolation]]
 * for why this suite avoids creating throwaway Lead/Person rows.
 */
function withTemporaryExtensionForCallButton(int $userId, string $temporaryExtension, callable $callback): void
{
    $repository = app(ZadarmaExtensionMappingRepository::class);

    $original = $repository->findExtensionByUserId($userId);

    $repository->saveMappings([$userId => $temporaryExtension]);

    try {
        $callback();
    } finally {
        $repository->saveMappings([$userId => $original]);
    }
}

it('injects the call button when the lead has a phone number and the user can use the phone', function () {
    $admin = getDefaultAdmin();

    withTemporaryExtensionForCallButton($admin->id, 'callbtn-test-101', function () use ($admin) {
        test()->actingAs($admin);

        // `id` is required even though this test only cares about the phone:
        // other addons listen on this same event (WhatsApp injects its chat
        // tile here too) and a real Lead always has one.
        $lead = (object) [
            'id' => 1,
            'person' => (object) [
                'contact_numbers' => [
                    ['value' => '5219998887777', 'label' => 'work'],
                ],
            ],
        ];

        $manager = app(ViewRenderEventManager::class);

        $manager->handleRenderEvent('admin.leads.view.actions.after', ['lead' => $lead]);
        $html = $manager->render();

        expect($html)->toContain('zadarmaOpenPhonePopup');
        expect($html)->toContain('5219998887777');
    });
});

it('renders nothing when the lead has no phone number', function () {
    $admin = getDefaultAdmin();

    withTemporaryExtensionForCallButton($admin->id, 'callbtn-test-101', function () use ($admin) {
        test()->actingAs($admin);

        $lead = (object) ['id' => 1, 'person' => (object) ['contact_numbers' => []]];

        $manager = app(ViewRenderEventManager::class);

        $manager->handleRenderEvent('admin.leads.view.actions.after', ['lead' => $lead]);

        expect($manager->render())->not->toContain('zadarmaOpenPhonePopup');
    });
});
