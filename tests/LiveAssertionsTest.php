<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
use InertiaLive\InertiaLive;
use PHPUnit\Framework\AssertionFailedError;

use function InertiaLive\live;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    view()->addLocation(__DIR__.'/Fixtures/views');
    Route::get('/docs/{uuid}', fn (string $uuid) => InertiaLive::render('Documents/Show', [
        'document' => live("documents.{$uuid}", ['uuid' => $uuid]),
        'activity' => live("documents.{$uuid}", ['a']),
        'title' => 'Static',
    ]));
    Route::get('/plain', fn () => Inertia::render('Plain', ['title' => 'x']));
});

it('asserts a binding from an assertInertia callback', function () {
    $this->get('/docs/abc')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Documents/Show')
        ->assertLive('documents.abc', ['activity', 'document'])
        ->assertLive('documents.abc')
        ->assertNotLive('folders.abc')
        ->where('title', 'Static'));
});

it('asserts a binding on the response', function () {
    $this->get('/docs/abc')
        ->assertLive('documents.abc', ['document', 'activity'])
        ->assertNotLive('documents.other');
});

it('asserts on an Inertia request without a root view', function () {
    $this->get('/docs/abc', ['X-Inertia' => 'true'])->assertLive('documents.abc', ['document', 'activity']);
});

it('asserts that a page is not live', function () {
    $this->get('/plain')->assertNotLive();
    $this->get('/plain')->assertInertia(fn (AssertableInertia $page) => $page->assertNotLive());
});

it('fails when the topic is not bound', function () {
    $this->get('/docs/abc')->assertLive('documents.zzz');
})->throws(AssertionFailedError::class, 'Expected the page to be live on topic [documents.zzz]; its topics are [documents.abc]');

it('fails when the props differ', function () {
    $this->get('/docs/abc')->assertLive('documents.abc', ['document']);
})->throws(AssertionFailedError::class, 'is bound to props [document, activity], not [document]');

it('fails when the page is live and should not be', function () {
    $this->get('/docs/abc')->assertNotLive();
})->throws(AssertionFailedError::class, 'no live bindings');

it('fails assertNotLive for a bound topic', function () {
    $this->get('/docs/abc')->assertNotLive('documents.abc');
})->throws(AssertionFailedError::class, 'not to be live on topic [documents.abc]');
