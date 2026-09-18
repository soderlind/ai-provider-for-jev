# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-09-18

### Added

- Initial release.
- Settings page (**Settings → Jev (TypeSafe)**) for API key, model, and base URL, with a "Test connection" check.
- API key masking, with constant/environment fallback (`TYPESAFE_API_KEY`, `TYPESAFE_MODEL`, `TYPESAFE_ENDPOINT`).
- PHP helper API: `evaluate()`, `ask_noul()`, `ask_choice()`, `ask_score()`.
- `JevClient` wrapping the TypeSafe `/systemone` and `/models` endpoints.
- Authenticated REST proxy at `ai-provider-for-jev/v1/{systemone,models}` with a filterable capability gate.
- Browser client (`src/js/client.js`) for the REST proxy.
- Test suites: Pest with Brain Monkey (PHP) and Vitest (JavaScript).

### Requirements

- WordPress 6.8+ and PHP 8.3+.

[0.1.0]: https://github.com/soderlind/ai-provider-for-jev/releases/tag/0.1.0
