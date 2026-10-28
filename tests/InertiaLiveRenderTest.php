<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use InertiaLive\InertiaLive;

use function InertiaLive\live;

beforeEach(function () {
    Route::get('/docs/{uuid}', fn (string $uuid) => InertiaLive::render('Documents/Show', [
        'document' => live("documents.{$uuid}", fn () => ['uuid' => $uuid]),
        'activity' => live("documents.{$uuid}", fn () => ['a']),
        'folder' => live("folders.{$uuid}", ['name' => 'Inbox']),
        'title' => 'Static',
    ]));

    $this->inertia = ['X-Inertia' => 'true'];
});

it('derives the bindings from the live props, one per topic', function () {
    $response = $this->get('/docs/abc', $this->inertia)->assertOk();

    $response
        ->assertJsonCount(2, 'props._live.bindings')
        ->assertJsonPath('props._live.bindings.0.topic', 'documents.abc')
        ->assertJsonPath('props._live.bindings.0.channel', 'live.documents.abc')
        ->assertJsonPath('props._live.bindings.0.props', ['document', 'activity'])
        ->assertJsonPath('props._live.bindings.0.public', false)
        ->assertJsonPath('props._live.bindings.1.topic', 'folders.abc')
        ->assertJsonPath('props._live.bindings.1.props', ['folder']);
});

it('renders the unwrapped values', function () {
    $this->get('/docs/abc', $this->inertia)
        ->assertJsonPath('props.document', ['uuid' => 'abc'])
        ->assertJsonPath('props.activity', ['a'])
        ->assertJsonPath('props.folder', ['name' => 'Inbox'])
        ->assertJsonPath('props.title', 'Static');
});

it('keeps _live and resolves only the requested props on a partial reload', function () {
    $this->get('/docs/abc', $this->inertia + [
        'X-Inertia-Partial-Component' => 'Documents/Show',
        'X-Inertia-Partial-Data' => 'activity',
    ])
        ->assertOk()
        ->assertJsonPath('props.activity', ['a'])
        ->assertJsonMissingPath('props.document')
        ->assertJsonPath('props._live.bindings.0.topic', 'documents.abc');
});

it('accepts Inertia prop types inside live()', function () {
    Route::get('/lazy', fn () => InertiaLive::render('Lazy', [
        'stats' => live('stats.1', Inertia::optional(fn () => ['n' => 1])),
    ]));

    $this->get('/lazy', $this->inertia)
        ->assertOk()
        ->assertJsonMissingPath('props.stats')
        ->assertJsonPath('props._live.bindings.0.props', ['stats']);

    $this->get('/lazy', $this->inertia + [
        'X-Inertia-Partial-Component' => 'Lazy',
        'X-Inertia-Partial-Data' => 'stats',
    ])->assertJsonPath('props.stats', ['n' => 1]);
});

it('adds no _live prop when nothing is live', function () {
    Route::get('/plain', fn () => InertiaLive::render('Plain', ['title' => 'x']));

    $this->get('/plain', $this->inertia)->assertOk()->assertJsonMissingPath('props._live');
});

it('rejects a live prop with no name', function () {
    InertiaLive::render('X', [live('a.1', 'v')]);
})->throws(InvalidArgumentException::class, 'needs a named prop');

it('rejects a prop wrapped in live() twice', function () {
    InertiaLive::render('X', ['a' => live('a.1', live('b.1', 'v'))]);
})->throws(InvalidArgumentException::class, 'wrapped in live() twice');
