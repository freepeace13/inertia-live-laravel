# Changelog

Server-side changes for `freepeace13/inertia-live-laravel`. Client changes are tracked in the [main repository](https://github.com/freepeace13/inertia-live/blob/main/CHANGELOG.md).

## Unreleased

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
