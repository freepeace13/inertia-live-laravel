# inertia-live-laravel (server adapter)

`freepeace13/inertia-live-laravel`: real-time, live-updating Inertia pages for Laravel, driven by Spatie Event Sourcing projections. Declare which projection changes affect which page props; the package broadcasts a small "topic changed" signal and the client reloads only the affected props.

PHP 8.3+, Laravel 12/13, Inertia 2/3. Source in `src/`: `LiveManager` and the `Live` facade, `#[LiveTopic]` (`Attributes/`), `EmitsLiveChanges` (`Concerns/`), `ChangeBuffer`, `ChangeFlusher`, `TopicResolver`, `LiveBindings` and `LiveResponseMacro` (`->live()`), `Cursor/` (`CursorRepository`, `CacheCursorRepository`), `Broadcasting/LiveChangeBroadcast`, `Jobs/SendTrailingSignal`, `Testing/LiveFake`, config in `config/`.

## Commands

```sh
composer install
composer test      # pest
composer lint      # pint --test
composer format    # pint
composer analyse   # phpstan, memory limit 1G
```

## The release program (read this first for anything about releases or 1.0)

The source of truth is the docs repo, `../inertia-live-docs` (local sibling checkout; GitHub `freepeace13/inertia-live-docs`):

- `release-policy.md`: versioning, branches, support window, release flow
- `roadmap.md`: the road to 1.0, definition of done, open questions
- `decision-log.md`: numbered decisions (D-001 and up)

A private progress tracker mirrors these: https://claude.ai/artifact/PjKwna6Tty3ZnZFKneBjhT (owner access). When you change policy, plan or decisions: update the docs repo first, then the tracker, in the same piece of work. If they disagree, the docs win.

Related repos (siblings): `../inertia-live` (client packages), `../inertia-live-docs`, `../inertia-live-tooling` (release script, reusable workflows, Claude plugin).

## Rules for this repo

- **Never tag or push tags manually.** Releases go through the release workflow (see the policy). Composer reads the version from the git tag; Packagist picks tags up.
- **Server minors match client minors until 1.0:** Composer `0.N.x` pairs with npm `0.N.x`. A breaking protocol change bumps the minor on both sides in the same release window.
- **Changelog:** every user-visible change adds an entry under `## Unreleased` in `CHANGELOG.md`. Client changes are tracked in the client repo's changelog.
- **Wire-protocol changes** (signal payload, `_live` read, cursor or version semantics) must be made together with `../inertia-live` and the spec in the docs repo. Do not change one side alone.
- The cursor store must support atomic `increment`; signal versions are per-topic sequences taken at flush time (see `design-decisions.md` in the docs repo). Do not reintroduce stored event ids as versions.
- Behavior and config changes update the matching doc in the docs repo in the same piece of work (`v1/configuration.md`, `v1/topics.md`, `v1/projectors.md`, and so on). Link to them as `.../inertia-live-docs/blob/main/v1/<file>.md`.
- `/docs/` is gitignored: generated specs and plans stay local. Put anything that must survive in the decision log, a README or this file.
- **The response factory** (`InertiaLive::render()` with `live()` props, D-019) is built; `->live()` and `LiveResponseMacro` stay as the low-level API. Items tagged `@internal` or `@experimental` follow the tiers in `v1/stable-api.md` (D-023); keep the tags in step with that list.
- Match the surrounding code's style; Pint and PHPStan must pass.
- **No AI attribution** anywhere: no `Co-Authored-By` trailers, no "Generated with" footers, no mentions in commits, PRs, changelogs, docs or code comments.

## Status

The package is at 0.1.x and the 1.0 scope is decided (D-011), but no API is frozen until the contract phase lists it; check `roadmap.md`.

## Keeping the tracker in sync

Tracker: https://claude.ai/artifact/PjKwna6Tty3ZnZFKneBjhT. Update it whenever you finish, start, block or add work, or record a decision or note, in the same piece of work. Never create a second tracker artifact.

- **State** (phases, tasks, decisions, notes) lives in the artifact's database. Use the `ArtifactData` tool with the tracker URL; pin writes to documents you have read (`if_version`). Task ids are stable slugs (`t-...`); statuses are `todo`, `doing`, `done`, `blocked`.
- **Page text or layout:** read the artifact (`Artifact` with `action: "read"` and the URL) to get its current HTML, change it, and publish with `url` set so the link stays the same.
- **Order:** docs repo first (`decision-log.md`, `roadmap.md`, `release-policy.md`), then the tracker. The docs win on conflict.
