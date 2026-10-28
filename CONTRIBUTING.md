# Contributing

Thanks for helping. This repository holds the Laravel server adapter. The client is [inertia-live](https://github.com/freepeace13/inertia-live) and the docs and release program are in [inertia-live-docs](https://github.com/freepeace13/inertia-live-docs).

## Setup

```sh
composer install
composer test      # Pest
composer lint      # Pint (`composer format` fixes)
composer analyse   # PHPStan
```

## Rules

- Put a failing test first. Tests use Orchestra Testbench.
- A user-visible change adds an entry under `## Unreleased` in `CHANGELOG.md`.
- A change to the wire protocol (signal payload, `_live`, cursors, `X-Socket-ID`) needs the matching client change and the spec in `inertia-live-docs/v1/protocol.md`, and the shared fixture `v1/fixtures/protocol-1.json` if a shape moves (keep `tests/Fixtures/contract/protocol-1.json` identical). Do not change one side alone.
- Items marked `@internal` carry no promise; `@experimental` ones may change in a minor. See the stable API list in the docs repo.
- Releases go through the release workflow. Do not tag or publish by hand.
