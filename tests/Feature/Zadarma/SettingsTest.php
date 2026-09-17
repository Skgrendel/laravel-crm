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

it('rejects testing the connection without any credentials configured', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->post(route('admin.settings.zadarma.test_connection'), [])
        ->assertStatus(422);
});
