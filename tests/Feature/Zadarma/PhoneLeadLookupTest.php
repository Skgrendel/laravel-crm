<?php

it('reports no match for a phone number that belongs to no Lead', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->getJson(route('admin.zadarma.phone.lookup_lead', ['number' => '10000000000']))
        ->assertOK()
        ->assertJson(['found' => false]);
});

it('requires a number to look up a lead', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->getJson(route('admin.zadarma.phone.lookup_lead'))
        ->assertStatus(422);
});
