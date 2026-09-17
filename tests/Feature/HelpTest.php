<?php

/**
 * Asserted through the translator rather than against English copy: this
 * installation runs in Spanish (`APP_LOCALE=es`), so hardcoded English made
 * the test fail on wording while the page itself was fine. Going through
 * `trans()` keeps it testing what it means to test — that the help page
 * renders its sections — on an install in any language.
 */
it('shows the help page to an authenticated admin', function () {
    $admin = getDefaultAdmin();

    $response = test()->actingAs($admin)
        ->get(route('admin.help.index'))
        ->assertOk();

    foreach ([
        'admin::app.help.index.title',
        'admin::app.help.index.services.title',
        'admin::app.help.index.resources.title',
        'admin::app.help.index.still-need-help-title',
        'admin::app.help.index.contact-us',
    ] as $key) {
        $response->assertSee(trans($key), false);
    }

    // Locale-independent, so it pins the actual content too, not just labels.
    $response->assertSee('krayincrm.com/cloud-hosting');
});
