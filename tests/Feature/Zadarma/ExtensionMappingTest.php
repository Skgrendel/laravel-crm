<?php

use Addons\Zadarma\Repositories\ZadarmaExtensionMappingRepository;

it('saves an extension mapping for a user and can resolve it back', function () {
    $admin = getDefaultAdmin();

    $repository = app(ZadarmaExtensionMappingRepository::class);

    $original = $repository->getAllKeyedByUserId()->get($admin->id)?->extension;

    test()->actingAs($admin)
        ->putJson(route('admin.settings.zadarma.extensions.update'), [
            'mappings' => [
                ['user_id' => $admin->id, 'extension' => 'test-101'],
            ],
        ])
        ->assertOK();

    expect($repository->findUserIdByExtension('test-101'))->toBe($admin->id);

    // Restore prior state.
    $repository->saveMappings([$admin->id => $original]);
});

it('rejects saving a mapping when two agents share the same extension', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->putJson(route('admin.settings.zadarma.extensions.update'), [
            'mappings' => [
                ['user_id' => 9001, 'extension' => 'dup-101'],
                ['user_id' => 9002, 'extension' => 'dup-101'],
            ],
        ])
        ->assertStatus(422);
});

it('clears a mapping when the extension is left blank', function () {
    $admin = getDefaultAdmin();

    $repository = app(ZadarmaExtensionMappingRepository::class);

    $original = $repository->findExtensionByUserId($admin->id);

    $repository->saveMappings([$admin->id => 'temp-202']);
    expect($repository->findUserIdByExtension('temp-202'))->toBe($admin->id);

    test()->actingAs($admin)
        ->putJson(route('admin.settings.zadarma.extensions.update'), [
            'mappings' => [
                ['user_id' => $admin->id, 'extension' => ''],
            ],
        ])
        ->assertOK();

    expect($repository->findUserIdByExtension('temp-202'))->toBeNull();

    // Restore prior state.
    $repository->saveMappings([$admin->id => $original]);
});
