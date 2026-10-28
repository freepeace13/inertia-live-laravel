<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use InertiaLive\Cursor\CursorRepository;
use InertiaLive\Facades\Live;

function cursorsUser(int $id = 1): User
{
    return (new User)->forceFill(['id' => $id]);
}

beforeEach(function () {
    Live::authorize('documents.{uuid}', fn ($user, string $uuid) => $user->id === 1 && $uuid !== 'secret');
    Live::publicTopic('stats.{id}');
});

it('registers the named route at the configured path', function () {
    expect(route('inertia-live.cursors', [], false))->toBe('/inertia-live/cursors');
});

it('returns the current cursor of topics the user may subscribe to', function () {
    $cursor = app(CursorRepository::class)->next('documents.abc');

    $this->actingAs(cursorsUser())
        ->getJson('/inertia-live/cursors?topics[]=documents.abc&topics[]=stats.7')
        ->assertOk()
        ->assertExactJson(['cursors' => ['documents.abc' => $cursor, 'stats.7' => 0]]);
});

it('omits topics the user may not subscribe to', function () {
    $this->actingAs(cursorsUser(2))
        ->getJson('/inertia-live/cursors?topics[]=documents.abc&topics[]=stats.7')
        ->assertOk()
        ->assertExactJson(['cursors' => ['stats.7' => 0]]);

    $this->actingAs(cursorsUser())
        ->getJson('/inertia-live/cursors?topics[]=documents.secret')
        ->assertOk()
        ->assertExactJson(['cursors' => []]);
});

it('gives guests only public topics', function () {
    $this->getJson('/inertia-live/cursors?topics[]=documents.abc&topics[]=stats.7')
        ->assertOk()
        ->assertExactJson(['cursors' => ['stats.7' => 0]]);
});

it('omits topics with no authorizer', function () {
    $this->actingAs(cursorsUser())
        ->getJson('/inertia-live/cursors?topics[]=invoices.1')
        ->assertOk()
        ->assertExactJson(['cursors' => []]);
});

it('encodes an empty result as an object', function () {
    $response = $this->getJson('/inertia-live/cursors?topics[]=documents.abc');

    expect($response->getContent())->toBe('{"cursors":{}}');
});

it('treats an authorizer that throws as a denial', function () {
    Live::authorize('boom.{id}', fn () => throw new RuntimeException('nope'));
    Live::authorize('typed.{id}', fn (object $user, int $id) => true);

    $this->actingAs(cursorsUser())
        ->getJson('/inertia-live/cursors?topics[]=boom.1&topics[]=typed.1')
        ->assertOk()
        ->assertExactJson(['cursors' => []]);
});

it('denies a callback that type-hints a bound model, which gets a string here', function () {
    Live::authorize('typed.{doc}', fn (object $user, Collection $doc) => true);

    $this->actingAs(cursorsUser())
        ->getJson('/inertia-live/cursors?topics[]=typed.1')
        ->assertOk()
        ->assertExactJson(['cursors' => []]);
});

it('logs a throwing authorizer when debug is on', function () {
    config(['inertia-live.debug' => true]);
    Log::spy();
    Live::authorize('boom.{id}', fn () => throw new RuntimeException('nope'));

    Live::canSubscribe(cursorsUser(), 'boom.1');

    Log::shouldHaveReceived('debug')->once();
});

it('throttles by default', function () {
    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/inertia-live/cursors?topics[]=stats.1')->assertOk();
    }

    $this->getJson('/inertia-live/cursors?topics[]=stats.1')->assertStatus(429);
});

it('answers each topic once', function () {
    $this->actingAs(cursorsUser())
        ->getJson('/inertia-live/cursors?topics[]=documents.abc&topics[]=documents.abc')
        ->assertOk()
        ->assertExactJson(['cursors' => ['documents.abc' => 0]]);
});

it('is not cacheable', function () {
    $this->getJson('/inertia-live/cursors?topics[]=stats.1')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('rejects a missing, empty, oversized or malformed topic list', function (string $query) {
    $this->getJson('/inertia-live/cursors'.$query)->assertStatus(422);
})->with([
    'none' => [''],
    'not a list' => ['?topics=documents.abc'],
    'empty topic' => ['?topics[]='],
    'nested' => ['?topics[][]=a'],
    'keyed' => ['?topics[x]=documents.abc'],
    'too many' => ['?'.implode('&', array_map(fn (int $i) => "topics[]=stats.$i", range(1, 51)))],
]);

it('accepts exactly the maximum number of topics', function () {
    $this->getJson('/inertia-live/cursors?'.implode('&', array_map(fn (int $i) => "topics[]=stats.$i", range(1, 50))))
        ->assertOk();
});

it('advertises the endpoint in _live', function () {
    Route::get('/docs/{uuid}', fn (string $uuid) => Inertia::render('Documents/Show', ['document' => 1])
        ->live("documents.{$uuid}", only: ['document']));

    $this->get('/docs/abc', ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertJsonPath('props._live.cursors', '/inertia-live/cursors');
});

it('can ask the manager through the fake', function () {
    $fake = Live::fake();

    expect($fake->canSubscribe(cursorsUser(), 'documents.abc'))->toBeTrue()
        ->and($fake->canSubscribe(cursorsUser(2), 'documents.abc'))->toBeFalse();
});

it('can ask the manager directly', function () {
    expect(Live::canSubscribe(cursorsUser(), 'documents.abc'))->toBeTrue()
        ->and(Live::canSubscribe(cursorsUser(2), 'documents.abc'))->toBeFalse()
        ->and(Live::canSubscribe(null, 'documents.abc'))->toBeFalse()
        ->and(Live::canSubscribe(null, 'stats.1'))->toBeTrue();
});
