<?php

use Addons\Zadarma\Repositories\ZadarmaSettingRepository;

it('can see the zadarma settings page', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->get(route('admin.settings.zadarma.index'))
        ->assertOK()
        ->assertSee('Zadarma VoIP');
});

it('can toggle the addon on without touching stored credentials', function () {
    $admin = getDefaultAdmin();

    $repository = app(ZadarmaSettingRepository::class);

    $before = $repository->getSettings();

    test()->actingAs($admin)
        ->put(route('admin.settings.zadarma.update'), [
            'enabled' => '1',
        ])
        ->assertRedirect(route('admin.settings.zadarma.index'));

    $after = $repository->getSettings()->fresh();

    expect($after->enabled)->toBeTrue();
    expect($after->api_key)->toBe($before->api_key);

    // Restore the toggle so this test doesn't leave the addon enabled with
    // no credentials configured.
    $repository->update(['enabled' => $before->enabled], $after->id);
});

/**
 * Not tested at the HTTP level: `testConnection` falls back to whatever
 * credentials are already saved when the request fields are blank, and this
 * suite runs against the real dev database (no RefreshDatabase), which may
 * already have real Zadarma credentials saved. Asserting a 422 here would
 * either be flaky or require clearing real saved state — neither is safe.
 */
