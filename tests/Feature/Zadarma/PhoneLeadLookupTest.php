<?php

/**
 * The number is randomised rather than a memorable constant: since calls
 * from unknown numbers now capture a Lead, a fixed one is liable to have
 * been turned into a real Lead by another test in this suite.
 */
it('reports no match for a phone number that belongs to no Lead', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->getJson(route('admin.zadarma.phone.lookup_lead', ['number' => '19'.rand(100000000, 999999999)]))
        ->assertOK()
        ->assertJson(['found' => false]);
});

it('requires a number to look up a lead', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->getJson(route('admin.zadarma.phone.lookup_lead'))
        ->assertStatus(422);
});
