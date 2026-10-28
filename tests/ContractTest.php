<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use InertiaLive\Broadcasting\LiveChangeBroadcast;
use InertiaLive\Change;
use InertiaLive\Facades\Live;

// The fixture is shared with the client packages. The canonical copy is
// inertia-live-docs/v1/fixtures/protocol-1.json; both repos carry an identical copy.
function contractFixture(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/Fixtures/contract/protocol-1.json'), true, flags: JSON_THROW_ON_ERROR);
}

it('matches the canonical copy in the docs repo when it is checked out alongside', function () {
    $canonical = __DIR__.'/../../inertia-live-docs/v1/fixtures/protocol-1.json';

    if (! is_file($canonical)) {
        $this->markTestSkipped('The docs repo is not checked out next to this one.');
    }

    expect(file_get_contents(__DIR__.'/Fixtures/contract/protocol-1.json'))->toBe(file_get_contents($canonical));
});

it('declares protocol 1', function () {
    expect(contractFixture()['protocol'])->toBe(1);
});

it('broadcasts the signal payloads in the fixture', function (array $case) {
    $change = new Change($case['change']['topic'], $case['change']['props']);

    expect((new LiveChangeBroadcast($change, $case['change']['version']))->broadcastWith())->toBe($case['payload']);
})->with(fn () => array_map(fn (array $case) => [$case], contractFixture()['signals']));

it('builds the _live prop in the fixture', function () {
    $live = contractFixture()['live'];
    Live::authorize('documents.{id}', fn () => true);
    Route::get($live['route'], fn () => Inertia::render('Documents/Show', ['document' => [], 'activity' => []])
        ->live($live['topic'], only: $live['props']));

    $actual = $this->get($live['route'], ['X-Inertia' => 'true'])->assertOk()->json('props._live');
    $actual['bindings'][0]['cursor'] = $live['payload']['bindings'][0]['cursor'];

    expect($actual)->toBe($live['payload']);
});

it('answers the cursors request in the fixture', function () {
    $response = contractFixture()['cursorsResponse'];
    Live::authorize('documents.{id}', fn () => true);
    Live::publicTopic('stats.{id}');

    $request = $response['request'];
    $query = http_build_query(['topics' => $request['topics']]);

    $actual = $this->actingAs((new User)->forceFill(['id' => 1]))
        ->getJson($request['path'].'?'.$query)
        ->assertOk()
        ->json();

    // A topic that has issued no version reads 0; one that has reads the fixture's value once it is set.
    expect($actual['cursors']['stats.7'])->toBe($response['cursors']['stats.7']);
    $actual['cursors']['documents.9f1c2e'] = $response['cursors']['documents.9f1c2e'];

    expect($actual)->toBe(['cursors' => $response['cursors']]);
});
