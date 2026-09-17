<?php

/**
 * The inbox renders `v-whatsapp-thread` itself, but that component is
 * defined by the chat partial — which the Lead page normally brings in. If
 * that include ever goes missing the page still loads and the list still
 * works, and only the conversation pane silently stays blank. This pins the
 * whole chain.
 */
it('renders the inbox with the chat component available on the page', function () {
    $html = test()->actingAs(getDefaultAdmin())
        ->get(route('admin.whatsapp.inbox.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('v-whatsapp-inbox');

    // Defined, not just referenced.
    expect($html)->toContain("app.component('v-whatsapp-thread'");
    expect($html)->toContain('id="v-whatsapp-thread-template"');

    /**
     * No *instance* of the tile component: its template definition is pushed
     * either way (that is how Vue templates work), but an actual
     * `<v-whatsapp-chat>` on this page would be an action tile with no Lead
     * behind it.
     */
    expect($html)->not->toContain('<v-whatsapp-chat ');

    /**
     * Same stacking rule as everywhere else in this addon: Vue drops style
     * and script tags left inside `#app`, so these have to come through the
     * layout's stacks.
     */
    expect(strpos($html, '.whatsapp-inbox-row {'))
        ->toBeLessThan(strpos($html, 'id="app"'));
});
