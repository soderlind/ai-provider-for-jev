# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-08

### Added

- Self-updates from GitHub releases through
  [`soderlind/wordpress-github-updater`](https://github.com/soderlind/wordpress-plugin-gitHub-updater),
  which checks for a new release every 6 hours and installs the
  `ai-provider-for-jev.zip` asset.
- GitHub Actions: a CI workflow that validates the Composer metadata and runs
  the Pest suite on PHP 8.3 and 8.4 plus the Vitest suite, and
  release-triggered and manual workflows that build `ai-provider-for-jev.zip`
  and attach it to the release.
- `.distignore`, so the release zip contains only the runtime plugin files and
  the production Composer dependencies.
- Developer guide (`docs/developer.md`) and benchmark results
  (`docs/benchmark.md`).
- Example child plugin (Jev Comment Triage) under `docs/examples/`.

### Changed

- Installation documents downloading the release zip instead of copying the
  plugin folder by hand.
- Pinned the development dependencies to Pest 4 so the supported PHP 8.3
  minimum stays testable, and tracked `composer.lock` for reproducible builds.

## [0.2.0] - 2026-09-18

### Added

- Browser client (`src/js/client.js`) for the REST proxy.
- Test suites: Pest with Brain Monkey (PHP) and Vitest (JavaScript).
- `README.md` and `CHANGELOG.md` documentation.

### Changed

- Raised the minimum requirements to WordPress 6.8 and PHP 8.3.

## [0.1.0] - 2026-09-18

### Added

- Initial release.
- Settings page (**Settings → Jev (TypeSafe)**) for API key, model, and base URL, with a "Test connection" check.
- API key masking, with constant/environment fallback (`TYPESAFE_API_KEY`, `TYPESAFE_MODEL`, `TYPESAFE_ENDPOINT`).
- PHP helper API: `evaluate()`, `ask_noul()`, `ask_choice()`, `ask_score()`.
- `JevClient` wrapping the TypeSafe `/systemone` and `/models` endpoints.
- Authenticated REST proxy at `ai-provider-for-jev/v1/{systemone,models}` with a filterable capability gate.

[1.0.0]: https://github.com/soderlind/ai-provider-for-jev/releases/tag/1.0.0
[0.2.0]: https://github.com/soderlind/ai-provider-for-jev/releases/tag/0.2.0
[0.1.0]: https://github.com/soderlind/ai-provider-for-jev/releases/tag/0.1.0
