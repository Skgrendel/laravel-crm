<?php

it('lists the account DID numbers from the real Zadarma API', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->getJson(route('admin.settings.zadarma.pbx.direct_numbers'))
        ->assertOK()
        ->assertJsonStructure(['numbers']);
});

it('reads the current call-forwarding status for a real extension', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->getJson(route('admin.settings.zadarma.pbx.redirection.show', '507778-101'))
        ->assertOK()
        ->assertJsonStructure(['current_status', 'pbx_id']);
});

it('returns a friendly error for redirection status of a nonexistent extension', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->getJson(route('admin.settings.zadarma.pbx.redirection.show', 'not-a-real-extension'))
        ->assertStatus(422);
});
