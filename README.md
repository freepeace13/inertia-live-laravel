# freepeace13/inertia-live-laravel

Live Inertia pages driven by [Spatie Event Sourcing](https://github.com/spatie/laravel-event-sourcing) projections. Declare once on the server which projection changes affect which page props; the package broadcasts a small "topic changed" signal and the client reloads only the affected props through the page's own controller.

The socket never carries model data, so policies, hidden attributes and per-user fields keep working unchanged.

## Part of Inertia Live

| Package | Install | Role |
| --- | --- | --- |
| **`freepeace13/inertia-live-laravel`** (this repo) | `composer require freepeace13/inertia-live-laravel` | Server: topics, projector trait, broadcasting, `->live()` |
| [`@freepeace13/inertia-live-core`](https://github.com/freepeace13/inertia-live/tree/main/packages/core) | `npm install @freepeace13/inertia-live-core` | Framework-agnostic client |
| [`@freepeace13/inertia-live-vue`](https://github.com/freepeace13/inertia-live/tree/main/packages/vue) | `npm install @freepeace13/inertia-live-vue` | Vue 3 plugin and `useLive()` |
| [`@freepeace13/inertia-live-react`](https://github.com/freepeace13/inertia-live/tree/main/packages/react) | `npm install @freepeace13/inertia-live-react` | React provider and `useLive()` |

Full documentation, a runnable demo and the client packages live in the [main repository](https://github.com/freepeace13/inertia-live).

## Requirements

- PHP 8.3, 8.4 or 8.5
- Laravel 12 or 13
- `inertiajs/inertia-laravel` ^2.0 or ^3.0
- `spatie/laravel-event-sourcing` ^7.14
- An Echo-compatible broadcaster (Reverb, Pusher or Ably)

## Installation

```bash
composer require freepeace13/inertia-live-laravel
```

The service provider is auto-discovered. Optionally publish the config:

```bash
php artisan vendor:publish --tag=inertia-live-config
```

Broadcasting must be set up (`php artisan install:broadcasting` if it is not). See [Installation](https://github.com/freepeace13/inertia-live/blob/main/docs/installation.md).

## Usage

### 1. Declare topics on events

```php
use Freepeace13\InertiaLive\Attributes\LiveTopic;

#[LiveTopic('documents.{documentUuid}', props: ['document', 'activity'])]
final class DocumentRenamed extends ShouldBeStored
{
    public function __construct(
        public readonly string $documentUuid,
        public readonly string $title,
    ) {}
}
```

`{placeholders}` are filled from the event's scalar properties. The attribute is repeatable and is private unless its pattern is registered with `Live::publicTopic()`. See [Topics](https://github.com/freepeace13/inertia-live/blob/main/docs/topics.md).

### 2. Mark changes in projectors

```php
use Freepeace13\InertiaLive\Concerns\EmitsLiveChanges;

final class DocumentProjector extends Projector
{
    use EmitsLiveChanges;

    public function onDocumentRenamed(DocumentRenamed $event): void
    {
        DocumentReadModel::whereUuid($event->documentUuid)->update(['title' => $event->title]);
    }
}
```

Signals are buffered, coalesced per topic and sent only after the handler returns and the database transaction commits. For events without the attribute, call `$this->liveChanged('documents.'.$uuid, ['document'])` inside a handler. See [Projectors](https://github.com/freepeace13/inertia-live/blob/main/docs/projectors.md).

### 3. Authorize the topic

```php
use Freepeace13\InertiaLive\Facades\Live;

Live::authorize('documents.{uuid}', fn (User $user, string $uuid) =>
    $user->can('view', Document::whereUuid($uuid)->firstOrFail())
);
```

Topics are broadcast on private channels (`private-live.{topic}`). A private topic without an authorizer fails closed: the signal is not sent and a warning is logged. See [Authorization](https://github.com/freepeace13/inertia-live/blob/main/docs/authorization.md).

### 4. Bind the topic to props

```php
return Inertia::render('Documents/Show', [
    'document' => DocumentResource::make($doc),
    'activity' => fn () => $doc->activity()->latest()->limit(20)->get(),
])->live("documents.{$doc->uuid}", only: ['document', 'activity']);
```

`->live()` adds a `_live` prop with the channel, bound props and current cursor. See [Page bindings](https://github.com/freepeace13/inertia-live/blob/main/docs/bindings.md).

## What the package provides

| Class | Responsibility |
| --- | --- |
| `Attributes\LiveTopic` | Declares which topic an event affects |
| `TopicResolver` | Resolves topic templates against event properties |
| `Concerns\EmitsLiveChanges` | Projector trait that records changes after handlers return |
| `ChangeBuffer` / `ReplayBuffer` | Coalesce changes per topic; hold changes during replays |
| `ChangeFlusher` | After commit: take the next sequence number, check authorizer, rate limit, broadcast |
| `Broadcasting\LiveChangeBroadcast` | `ShouldBroadcastNow` event named `live.changed`, payload `{ topic, version, props }` |
| `Cursor\CursorRepository` | Per-topic sequence numbers (default: cache store, needs atomic increment) |
| `LiveManager` / `Facades\Live` | `Live::authorize()` and the test fake |
| `LiveResponseMacro` / `LiveBindings` | `Inertia\Response::live()` and the `_live` prop |
| `Testing\LiveFake` | Recorder installed by `Live::fake()` |

## Correctness guarantees

| Risk | Rule |
| --- | --- |
| Signal before data is committed | Flushed in `DB::afterCommit`, after the projector handler returns |
| Queued or several projectors on a topic | Each signal takes the topic's next sequence number, so none is dropped as stale |
| Render races a signal | Cursor is the topic's latest sequence number; the client drops signals at or below it |
| Event bursts | One signal per topic per request or job |
| Sender's own action | Excluded via `X-Socket-ID`, which the client adapters send |
| Projector replay | Signals suppressed; optional final signal per topic |
| Rate limit | Over `max_signals_per_second`, signals collapse into one trailing signal (needs a queue worker) |
| Rolled-back transaction | Its changes are discarded, nothing is broadcast |

The cursor store needs atomic `increment` (Redis, database, Memcached). See [Consistency](https://github.com/freepeace13/inertia-live/blob/main/docs/consistency.md) and [Design decisions](https://github.com/freepeace13/inertia-live/blob/main/docs/design-decisions.md).

## Configuration

```php
// config/inertia-live.php
return [
    'enabled' => env('INERTIA_LIVE_ENABLED', true),
    'channel_prefix' => 'live',
    'cursor_store' => env('INERTIA_LIVE_CURSOR_STORE'), // null = default cache
    'max_signals_per_second' => 10,
    'replay' => ['suppress' => true, 'final_signal' => false],
    'debug' => env('APP_DEBUG', false), // logs every flushed signal
];
```

Invalid `channel_prefix` or `max_signals_per_second` values throw at boot. Details in [Configuration](https://github.com/freepeace13/inertia-live/blob/main/docs/configuration.md).

## Testing

```php
use Freepeace13\InertiaLive\Facades\Live;

Live::fake();

$this->post(route('documents.rename', $doc), ['title' => 'Q4 plan']);

Live::assertChanged("documents.{$doc->uuid}", props: ['document']);
Live::assertNothingChangedFor('documents.other-uuid');
Live::assertChangedTimes("documents.{$doc->uuid}", 1);
```

See [Testing](https://github.com/freepeace13/inertia-live/blob/main/docs/testing.md).

## Documentation

See the [documentation index](https://github.com/freepeace13/inertia-live/blob/main/docs/README.md) for installation, topics, projectors, authorization, consistency, configuration and troubleshooting.

## Development

```bash
composer install
composer test      # Pest + Orchestra Testbench
composer lint      # Pint (use composer format to fix)
composer analyse   # PHPStan / Larastan
```

## License

MIT
