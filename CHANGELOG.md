# Changelog

Server-side changes for `freepeace13/inertia-live-laravel`. Client changes are tracked in the [main repository](https://github.com/freepeace13/inertia-live/blob/main/CHANGELOG.md).

## Unreleased

### Added
- `php artisan inertia-live:install`: publishes and runs Spatie's event sourcing migrations when the table is missing, publishes the config, checks the broadcaster, the channel authorization route and the cursor cache, and prints the client setup (`--client=vue|react`, `--skip-migrations`, `--force`). See the installation guide in the docs repo.
- `assertLive($topic, $props)` and `assertNotLive($topic)` for controller tests, on the `assertInertia()` callback argument and on the test response. They check the page's `_live` bindings. See the testing guide in the docs repo.
- `InertiaLive::render($component, $props)` and the `InertiaLive\live($topic, $value)` prop wrapper: mark the props that depend on a topic and the `_live` bindings are derived from them, with no `only` list. `->live()` stays as the low-level API. See the bindings guide in the docs repo.
- A cursors endpoint (route `inertia-live.cursors`, default `GET /inertia-live/cursors`) that returns the current cursors of the requested topics the signed-in user may subscribe to, without running the page's controller. `_live` advertises it as `cursors`. Config keys `cursors.path` and `cursors.middleware`; `Live::canSubscribe($user, $topic)`. Authorizers are called with placeholder values as strings, and one that throws counts as a denial. See the protocol spec in the docs repo.
- Signals carry `protocol: 1`, the wire protocol version. The field is optional for receivers: a client that does not read it keeps working. See the protocol spec in the docs repo.

### Changed (experimental)
- `CursorRepository`, `LiveChangeBroadcast`, `Live::isPublic()`, `Live::hasAuthorizerFor()` and the `liveChanged()` hook are experimental and may change in a 1.x minor. Internal classes carry `@internal`. See the stable API list in the docs repo.

### Changed (breaking, pre-1.0)
- The root PHP namespace is `InertiaLive` instead of `Freepeace13\InertiaLive`. Update `use` statements (for example `InertiaLive\Attributes\LiveTopic`, `InertiaLive\Facades\Live`). The Composer package name `freepeace13/inertia-live-laravel` is unchanged.

## 0.1.0 - 2026-10-07

First release under the `freepeace13/inertia-live-laravel` name. The "Changed" entries below are relative to the earlier `inertia-live-projections` package.

### Changed (breaking, pre-1.0)
- Renamed from `freepeace13/inertia-live-projections` to `freepeace13/inertia-live-laravel`; extracted from the monorepo with history preserved.
- Signal versions are a per-topic sequence taken at flush time, not the stored event id. Fixes signals being dropped when several projectors, or concurrent queue workers, handle one topic. `CursorRepository::put()` becomes `next()`; `Change` no longer carries a version; `LiveChangeBroadcast` takes it as its second argument. The cursor store must support atomic `increment`. The `force` flag on replay signals is removed.
- Topic visibility is a property of the pattern: `Live::publicTopic('stats.{id}')` replaces `public:` on `#[LiveTopic]` and `->live()`.
- `->live($topic, only: [...])` now requires a non-empty `only`.

### Fixed
- Rate-limited signals collapse into one trailing signal instead of being dropped.
- `replay.final_signal` reaches open pages.
- Mixed public and private changes on one topic no longer coalesce to public.
- `Live::fake()` can be called repeatedly and no longer rebinds `LiveManager`.
- Rolled-back transactions no longer broadcast or advance the cursor.
- One failing topic no longer drops the other topics in a flush.
- Placeholder values are validated.
- Cursors expire after `cursor_ttl`; `TopicResolver` caches reflection.

### Added
- `#[LiveTopic]`, `EmitsLiveChanges`, after-commit `ChangeFlusher`, private-channel `LiveChangeBroadcast`, cache-backed cursors, `Live::authorize()`, the Inertia `->live()` macro and `_live` prop, replay suppression, `Live::fake()`.
- CI: PHP 8.3–8.5 × Laravel 12–13 × Inertia 2–3.

[Unreleased]: https://github.com/freepeace13/inertia-live-laravel/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/freepeace13/inertia-live-laravel/releases/tag/v0.1.0
