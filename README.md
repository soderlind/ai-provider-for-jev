# AI Provider for Jev

Connect WordPress to [TypeSafe](https://docs.typesafe.ai/introduction)'s **Jev** — the first "System One" model. Instead of generating text, Jev evaluates a *state* against typed *questions* and returns structured answers your code can use directly.

## Primitives

| Type       | Question                         | Returns                                  |
| ---------- | -------------------------------- | ---------------------------------------- |
| **Choice** | Choose one option from a list    | `choice`, `probabilities`, `confidence`  |
| **Score**  | Rate the state on a rubric       | `score`, `legend`, `probabilities`, `confidence` |
| **Noul**   | Is this statement true? (yes/no) | `noul` (0–1)                             |

## Requirements

- WordPress 6.4+
- PHP 8.1+
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

Define secrets in `wp-config.php` to keep them out of the database:

```php
define( 'TYPESAFE_API_KEY', 'sk-...' );
```

## PHP usage

```php
use function AiProviderForJev\ask_noul;
use function AiProviderForJev\ask_choice;
use function AiProviderForJev\ask_score;
use function AiProviderForJev\evaluate;

$ticket = "Help! My payouts have been failing for 3 days.";

// Noul — probability the answer is "yes" (0..1).
$urgent = ask_noul( $ticket, 'Does this message express urgency?' );

// Choice — pick one option, with a probability distribution.
$dept = ask_choice( $ticket, 'Which team should handle this?', [
	'billing'   => 'Payment or subscription issues',
	'technical' => 'Bugs or integration problems',
	'sales'     => 'Pricing or account questions',
] );
// $dept['choice'], $dept['probabilities'], $dept['confidence']

// Score — rate against an ordered rubric.
$frustration = ask_score( $ticket, 'How frustrated is the customer?', [
	'Calm', 'Frustrated', 'Very angry',
] );
// $frustration['score'], $frustration['legend'], $frustration['probabilities'], $frustration['confidence']
```

Ask several questions in one call with `evaluate()`:

```php
$response = evaluate( $ticket, [
	'is_urgent'   => [ 'type' => 'noul',  'instructions' => 'Does this convey urgency?' ],
	'frustration' => [ 'type' => 'score', 'instructions' => 'How frustrated is the customer?', 'criteria' => [ 'Calm', 'Frustrated', 'Very angry' ] ],
] );
// $response['answers']['is_urgent']['noul'], ...
```

Every helper returns a [`WP_Error`](https://developer.wordpress.org/reference/classes/wp_error/) on failure:

```php
$urgent = ask_noul( $ticket, 'Does this message express urgency?' );
if ( is_wp_error( $urgent ) ) {
	error_log( $urgent->get_error_message() );
}
```

## REST API

An authenticated proxy (uses the site's stored API key) is available for JS/front-end code:

- `POST /wp-json/ai-provider-for-jev/v1/systemone` — body `{ state, questions, model? }`
- `GET  /wp-json/ai-provider-for-jev/v1/models`

Access defaults to the `manage_options` capability. Filter it:

```php
add_filter( 'ai_provider_jev_rest_capability', fn() => 'edit_posts' );
```

## Links

- [TypeSafe documentation](https://docs.typesafe.ai/introduction)
- [API reference](https://docs.typesafe.ai/api)
- [Models](https://docs.typesafe.ai/models)

## License

GPL-2.0-or-later
