<?php

use Addons\Zadarma\Models\ZadarmaCallLog;
use Addons\Zadarma\Repositories\ZadarmaExtensionMappingRepository;

/**
 * This suite runs against the real dev database (no RefreshDatabase in this
 * project), which may already have a real extension mapped to the admin
 * user. Every test that changes the mapping must capture the original value
 * first and restore it afterwards — never assume it starts empty, and never
 * blindly clear it at the end.
 */
function withTemporaryExtension(int $userId, string $temporaryExtension, callable $callback): void
{
    $repository = app(ZadarmaExtensionMappingRepository::class);

    $original = $repository->findExtensionByUserId($userId);

    $repository->saveMappings([$userId => $temporaryExtension]);

    try {
        $callback($repository);
    } finally {
        $repository->saveMappings([$userId => $original]);
    }
}

it('shows a friendly message on the phone page when the user has no mapped extension', function () {
    $admin = getDefaultAdmin();

    withTemporaryExtension($admin->id, '', function () use ($admin) {
        test()->actingAs($admin)
            ->get(route('admin.zadarma.phone.show'))
            ->assertOK()
            ->assertSee(trans('zadarma::app.phone.no-extension'));
    });
});

it('rejects the webrtc-key request when the user has no mapped extension', function () {
    $admin = getDefaultAdmin();

    withTemporaryExtension($admin->id, '', function () use ($admin) {
        test()->actingAs($admin)
            ->getJson(route('admin.zadarma.phone.webrtc_key'))
            ->assertStatus(422);
    });
});

it('shows the floating phone button on admin pages only when the current user has a mapped extension', function () {
    $admin = getDefaultAdmin();

    withTemporaryExtension($admin->id, '', function () use ($admin) {
        test()->actingAs($admin)
            ->get(route('admin.dashboard.index'))
            ->assertOK()
            ->assertDontSee('zadarma-phone-button', false);
    });

    withTemporaryExtension($admin->id, 'phone-test-101', function () use ($admin) {
        test()->actingAs($admin)
            ->get(route('admin.dashboard.index'))
            ->assertOK()
            ->assertSee('zadarma-phone-button', false);
    });
});

it('shows recent calls for the mapped extension on the phone page', function () {
    $admin = getDefaultAdmin();

    withTemporaryExtension($admin->id, 'phone-recent-test-101', function () use ($admin) {
        $call = ZadarmaCallLog::create([
            'pbx_call_id'         => 'recent-test-'.uniqid(),
            'direction'           => 'incoming',
            'caller_id'           => '5219998887777',
            'called_number'       => 'phone-recent-test-101',
            'internal_extension'  => 'phone-recent-test-101',
            'duration'            => 37,
            'disposition'         => 'answered',
            'is_recorded'         => false,
        ]);

        try {
            test()->actingAs($admin)
                ->get(route('admin.zadarma.phone.show'))
                ->assertOK()
                ->assertSee('5219998887777');
        } finally {
            $call->delete();
        }
    });
});
