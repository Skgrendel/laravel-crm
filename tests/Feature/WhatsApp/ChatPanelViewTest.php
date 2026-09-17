<?php

/**
 * Regression test: the chat panel partial rendered as raw HTML text
 * (`view_render_event`), never through a real Lead page in the rest of this
 * suite, so a Blade compile error here went undetected until a real browser
 * hit it. Specifically: a JS comment containing the literal text "@once"
 * (meant descriptively, not as a directive) got parsed by Blade as a real
 * `@once` directive with no matching `@endonce`, breaking compilation.
 */
it('compiles the chat panel partial without a Blade syntax error', function () {
    $lead = (object) ['id' => 1];

    $html = view('whatsapp::partials.chat-panel', ['lead' => $lead])->render();

    expect($html)->toContain('v-whatsapp-chat');
    expect($html)->toContain('lead-id="1"');
});

/**
 * The partial compiling is only half the story: it also has to actually
 * reach the page, which is easy to lose silently — an earlier version hung
 * the panel off `admin.leads.view.right.before`, which fires at row level
 * between the fixed-width left panel and a `w-full` right panel, so it
 * rendered but collapsed to an invisible sliver. This also catches the hook
 * going dead if core renames the event.
 */
it('renders the chat tile and modal on a real Lead page', function () {
    $originalSettings = captureWhatsAppSettings();

    whatsAppSettingsRepository()->getSettings()->update([
        'enabled' => true,
        'webhook_secret' => 'test-webhook-secret',
        'default_owner_id' => getDefaultAdmin()->id,
    ]);

    $conversation = createChatFixtureConversation();

    try {
        $html = test()->actingAs(getDefaultAdmin())
            ->get(route('admin.leads.view', $conversation->lead_id))
            ->assertOK()
            ->getContent();

        expect($html)->toContain('v-whatsapp-chat');

        // The tile in the action row is the only entry point to the chat.
        expect($html)->toContain('whatsapp-chat-tile');

        /**
         * The modal must be teleported to body, like every core action modal.
         * Left in place it renders inside the Lead's `lg:sticky` left panel,
         * whose stacking context caps the modal's z-index — the right column
         * then paints over it (its activity rows use a `rotate-90` transform
         * for the old/new arrow, making a stacking context of their own).
         */
        expect($html)->toContain('<Teleport to="body">');

        /**
         * Vue mounts on `#app` and treats the DOM inside it as its template,
         * dropping any style/script tag it finds there. Addon assets have to
         * go through the layout's stacks instead, or they render into the
         * HTML and then silently vanish on mount — which is exactly how the
         * tile lost its color and the chat component never registered.
         *
         * The styles stack renders in `<head>`, before `#app` opens.
         */
        expect(strpos($html, '.whatsapp-chat-tile {'))
            ->toBeLessThan(strpos($html, 'id="app"'));

        /**
         * The scripts stack renders after `#app` closes. Core's own action
         * components push there too, so landing after one of them puts us in
         * the stack rather than inline inside the Vue root.
         */
        expect(strpos($html, 'id="v-whatsapp-chat-template"'))
            ->toBeGreaterThan(strpos($html, 'id="v-note-activity-template"'));
    } finally {
        deleteChatFixture($conversation);

        whatsAppSettingsRepository()->getSettings()->update($originalSettings);
    }
});
