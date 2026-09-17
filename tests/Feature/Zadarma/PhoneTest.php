<?php

use Addons\Zadarma\Repositories\ZadarmaExtensionMappingRepository;

it('shows a friendly message on the phone page when the user has no mapped extension', function () {
    $admin = getDefaultAdmin();

    $repository = app(ZadarmaExtensionMappingRepository::class);
    expect($repository->findExtensionByUserId($admin->id))->toBeNull();

    test()->actingAs($admin)
        ->get(route('admin.zadarma.phone.show'))
        ->assertOK()
        ->assertSee(trans('zadarma::app.phone.no-extension'));
});

it('rejects the webrtc-key request when the user has no mapped extension', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->getJson(route('admin.zadarma.phone.webrtc_key'))
        ->assertStatus(422);
});

it('shows the floating phone button on admin pages only when the current user has a mapped extension', function () {
    $admin = getDefaultAdmin();
    $repository = app(ZadarmaExtensionMappingRepository::class);

    test()->actingAs($admin)
        ->get(route('admin.dashboard.index'))
        ->assertOK()
        ->assertDontSee('zadarma-phone-button', false);

    $repository->saveMappings([$admin->id => 'phone-test-101']);

    test()->actingAs($admin)
        ->get(route('admin.dashboard.index'))
        ->assertOK()
        ->assertSee('zadarma-phone-button', false);

    $repository->saveMappings([$admin->id => '']);
});
