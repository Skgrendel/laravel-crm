<?php

use Illuminate\Support\Facades\DB;
use Webkul\Attribute\Models\Attribute;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Lead\Models\Lead;

/**
 * Runs against the real dev database, like the rest of this suite. The
 * reporting endpoints are read-only, so the only state written here is a set
 * of custom values on one Lead, removed again in `finally`.
 */
function reportApiToken(): string
{
    return getDefaultAdmin()->createToken('test-reports')->plainTextToken;
}

function reportApiGet(string $uri, ?string $token = null)
{
    $headers = ['Accept' => 'application/json'];

    if ($token) {
        $headers['Authorization'] = 'Bearer '.$token;
    }

    return test()->getJson($uri, $headers);
}

afterEach(function () {
    getDefaultAdmin()->tokens()->where('name', 'test-reports')->delete();
});

it('refuses the reporting feed without a token', function () {
    reportApiGet('/api/reports/leads')->assertUnauthorized();
});

it('publishes the custom field catalogue so reports need not hardcode it', function () {
    $fields = reportApiGet('/api/reports/lead-fields', reportApiToken())
        ->assertOk()
        ->json('data');

    $byCode = collect($fields)->keyBy('code');

    expect($byCode)->toHaveKey('ref_epayco');
    expect($byCode['ref_epayco']['type'])->toBe('text');

    // A dropdown has to publish its options, or a report can't know what
    // values to expect for it.
    expect($byCode['nivel_intencion']['options'])->toContain('Alto');
});

it('returns leads with their custom fields keyed by attribute code', function () {
    $lead = Lead::orderByDesc('id')->first();

    if (! $lead) {
        test()->markTestSkipped('No hay Leads en la base de dev.');
    }

    $option = Attribute::where('code', 'nivel_intencion')
        ->where('entity_type', 'leads')
        ->first()
        ->options
        ->firstWhere('name', 'Alto');

    $codes = ['ref_epayco', 'nivel_intencion'];

    try {
        app(AttributeValueRepository::class)->save([
            'entity_type' => 'leads',
            'entity_id'   => $lead->id,
            'ref_epayco'  => 'EPY-TEST-0001',
            'nivel_intencion' => $option->id,
        ]);

        $row = collect(
            reportApiGet('/api/reports/leads?per_page=200', reportApiToken())->assertOk()->json('data')
        )->firstWhere('id', $lead->id);

        expect($row['custom_fields']['ref_epayco'])->toBe('EPY-TEST-0001');

        /**
         * The label, not the option id: a report can't group by `4`, and
         * that number changes if the options are ever re-created.
         */
        expect($row['custom_fields']['nivel_intencion'])->toBe('Alto');
    } finally {
        DB::table('attribute_values')
            ->where('entity_type', 'leads')
            ->where('entity_id', $lead->id)
            ->whereIn('attribute_id', Attribute::whereIn('code', $codes)->where('entity_type', 'leads')->pluck('id'))
            ->delete();
    }
});

it('filters by a chosen date field so each report can pick its own window', function () {
    $token = reportApiToken();

    // Nothing was created in the far past, so the window must come back empty
    // — proving the filter is applied rather than ignored.
    reportApiGet('/api/reports/leads?from=2000-01-01&to=2000-12-31', $token)
        ->assertOk()
        ->assertJsonCount(0, 'data');

    reportApiGet('/api/reports/leads?date_field=not_a_column', $token)
        ->assertStatus(422);
});
