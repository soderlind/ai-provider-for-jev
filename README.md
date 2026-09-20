# AI Provider for Jev

Connect WordPress to [TypeSafe](https://docs.typesafe.ai/introduction)'s **Jev** — the first "System One" model. Instead of generating text, Jev evaluates a *state* against typed *questions* and returns structured answers your code can use directly.

## Primitives

| Type       | Question                         | Returns                                  |
| ---------- | -------------------------------- | ---------------------------------------- |
| **Choice** | Choose one option from a list    | `choice`, `probabilities`, `confidence`  |
| **Score**  | Rate the state on a rubric       | `score`, `legend`, `probabilities`, `confidence` |
| **Noul**   | Is this statement true? (yes/no) | `noul` (0–1)                             |

## Requirements

- WordPress 6.8+
- PHP 8.3+
- A TypeSafe API key ([console.typesafe.ai](https://console.typesafe.ai/settings/keys))

## Installation

1. Copy this folder to `wp-content/plugins/ai-provider-for-jev`.
2. Activate **AI Provider for Jev** in Plugins.
3. Go to **Settings → Jev (TypeSafe)** and enter your API key, then click **Test connection**.

## Configuration

Values resolve from the settings page first, then fall back to constants/environment variables:

| Setting      | Option / field | Constant / env var  | Default                        |
| ------------ | -------------- | ------------------- | ------------------------------ |
| API key      | API Key        | `TYPESAFE_API_KEY`  | —                              |
| Model        | Model          | `TYPESAFE_MODEL`    | `jev-latest`                   |
| API base URL | API Base URL   | `TYPESAFE_ENDPOINT` | `https://api.typesafe.ai/v1`   |
| Decisions path | Decisions Path | `TYPESAFE_DECISIONS_PATH` | `/systemone` |

Define secrets in `wp-config.php` to keep them out of the database:

```php
define( 'TYPESAFE_API_KEY', 'sk-...' );
```

### Using OpenRouter instead of TypeSafe directly

The plugin can also talk to Jev through [OpenRouter](https://openrouter.ai/~typesafe/jev-latest),
which proxies the same System One decisions API. Set:

| Setting        | Value                                          |
| -------------- | ----------------------------------------------- |
| API key        | Your OpenRouter key (or `OPENROUTER_API_KEY`)   |
| API base URL   | `https://openrouter.ai/api/alpha`               |
| Decisions path | `/decisions`                                    |
| Model          | `~typesafe/jev-latest`                          |

When the base URL points at `openrouter.ai`, the plugin automatically adds the
optional `HTTP-Referer`/`X-Title` headers OpenRouter uses for its app leaderboards.

## Usage

Call Jev from PHP with the helper API:

```php
use function AiProviderForJev\ask_noul;

$urgent = ask_noul( 'Help! My payouts have failed for 3 days.', 'Does this express urgency?' );
// 0.0–1.0, or a WP_Error on failure
```

There is also `ask_choice()`, `ask_score()`, `evaluate()` (many questions at
once), a `JevClient` class, an authenticated REST proxy, and a JavaScript
client. See the **[Developer guide](docs/developer.md)** for the full API,
REST endpoints, and hooks.

## Links

- [TypeSafe documentation](https://docs.typesafe.ai/introduction)
- [API reference](https://docs.typesafe.ai/api)
- [Models](https://docs.typesafe.ai/models)

## License

GPL-2.0-or-later
