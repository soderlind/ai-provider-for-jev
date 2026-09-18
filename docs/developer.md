# Developer guide

How to call [Jev](https://docs.typesafe.ai/introduction) from your own WordPress
code — the PHP helper API, the `JevClient` class, the REST proxy, the bundled
JavaScript client, and the plugin's hooks.

- [Concepts](#concepts)
- [Configuration](#configuration)
- [PHP helper API](#php-helper-api)
- [Direct client usage](#direct-client-usage)
- [REST API](#rest-api)
- [JavaScript client](#javascript-client)
- [Hooks and filters](#hooks-and-filters)
- [Example child plugin](#example-child-plugin)
- [Testing](#testing)

## Concepts

Jev evaluates a **state** (the content) against a map of typed **questions** and
returns one structured **answer** per question. There are three question types:

| Type       | `type`   | Extra field `criteria`                     | Answer fields                                    |
| ---------- | -------- | ------------------------------------------ | ------------------------------------------------ |
| **Noul**   | `noul`   | optional `{ true, false }` descriptions    | `noul` (0–1)                                     |
| **Choice** | `choice` | required map `option => description\|null` | `choice`, `probabilities`, `confidence`          |
| **Score**  | `score`  | required ordered array (≥ 2 levels)        | `score`, `legend`, `probabilities`, `confidence` |

Ask as many questions as you like in a single request — each is evaluated in
isolation against the same state.

## Configuration

Every value resolves from the settings page first, then a constant or
environment variable. Define secrets in `wp-config.php` to keep them out of the
database:

```php
define( 'TYPESAFE_API_KEY', 'sk-...' );
define( 'TYPESAFE_MODEL', 'jev-latest' );          // optional
define( 'TYPESAFE_ENDPOINT', 'https://api.typesafe.ai/v1' ); // optional
```

Read the effective configuration at runtime:

```php
use AiProviderForJev\Settings\SettingsManager;

$settings = SettingsManager::instance();
$settings->get_model();       // e.g. "jev-latest"
$settings->get_endpoint();    // e.g. "https://api.typesafe.ai/v1"
$settings->is_configured();   // true when an API key is available
```

## PHP helper API

The helper functions live in the `AiProviderForJev` namespace and are loaded on
every request. Each returns the answer payload, or a
[`WP_Error`](https://developer.wordpress.org/reference/classes/wp_error/) on
failure.

```php
use function AiProviderForJev\ask_noul;
use function AiProviderForJev\ask_choice;
use function AiProviderForJev\ask_score;
use function AiProviderForJev\evaluate;
```

### `ask_noul()` — yes/no probability

```php
ask_noul( string|array|object $state, string $instructions, ?array $criteria = null, ?string $model = null ): float|WP_Error
```

```php
$state = 'Help! My payouts have been failing for 3 days.';

$probability = ask_noul( $state, 'Does this message express urgency?' );
// e.g. 0.92

if ( is_wp_error( $probability ) ) {
	error_log( $probability->get_error_message() );
} elseif ( $probability > 0.8 ) {
	// treat as urgent
}
```

Add `criteria` to define what "yes" and "no" mean:

```php
$probability = ask_noul(
	$state,
	'Is this message urgent?',
	[
		'true'  => 'Explicitly time-sensitive or blocking the customer',
		'false' => 'No urgency expressed',
	]
);
```

### `ask_choice()` — pick one option

```php
ask_choice( string|array|object $state, string $instructions, array $criteria, ?string $model = null ): array|WP_Error
```

```php
$answer = ask_choice(
	$state,
	'Which team should handle this?',
	[
		'billing'   => 'Payments, invoicing, refunds',
		'technical' => 'Bugs, outages, integrations',
		'sales'     => 'Pricing, upgrades, new accounts',
	]
);

// $answer = [
//   'choice'        => 'billing',
//   'probabilities' => [ 'billing' => 0.84, 'technical' => 0.15, 'sales' => 0.01 ],
//   'confidence'    => 0.59,
// ];

if ( ! is_wp_error( $answer ) && $answer['confidence'] < 0.5 ) {
	// low confidence — route to a human
}
```

Pass `null` for an option that needs no description:

```php
$answer = ask_choice( $state, 'Sentiment?', [
	'positive' => null,
	'neutral'  => null,
	'negative' => null,
] );
```

### `ask_score()` — rate on a rubric

```php
ask_score( string|array|object $state, string $instructions, array $criteria, ?string $model = null ): array|WP_Error
```

```php
$answer = ask_score(
	$state,
	'How frustrated is the customer?',
	[ 'Calm, just stating facts', 'Frustrated but civil', 'Very angry, strong language' ]
);

// $answer = [
//   'score'         => 1.6,
//   'legend'        => [ '0' => 'Calm...', '1' => 'Frustrated...', '2' => 'Very angry...' ],
//   'probabilities' => [ '0' => 0.05, '1' => 0.30, '2' => 0.65 ],
//   'confidence'    => 0.78,
// ];
```

### `evaluate()` — many questions in one call

```php
evaluate( string|array|object $state, array $questions, ?string $model = null ): array|WP_Error
```

```php
$response = evaluate( $state, [
	'is_urgent'   => [
		'type'         => 'noul',
		'instructions' => 'Does this convey urgency?',
	],
	'department'  => [
		'type'         => 'choice',
		'instructions' => 'Which team should handle this?',
		'criteria'     => [
			'billing'   => 'Payment or subscription issues',
			'technical' => 'Bugs or integration problems',
			'sales'     => 'Pricing or account questions',
		],
	],
	'frustration' => [
		'type'         => 'score',
		'instructions' => 'How frustrated is the customer?',
		'criteria'     => [ 'Calm', 'Frustrated', 'Very angry' ],
	],
] );

if ( is_wp_error( $response ) ) {
	return;
}

$response['answers']['is_urgent']['noul'];      // 0.999
$response['answers']['department']['choice'];   // 'billing'
$response['answers']['frustration']['score'];   // 1.035
$response['usage'];                             // [ 'input_tokens' => …, 'output_tokens' => … ]
```

### Structured state

`state` can be a string, an array, or an object — useful for chat logs or
records:

```php
$response = evaluate(
	[
		[ 'role' => 'customer', 'text' => 'My card was charged twice.' ],
		[ 'role' => 'agent', 'text' => 'Let me check that for you.' ],
	],
	[ 'refund' => [ 'type' => 'noul', 'instructions' => 'Does the customer want a refund?' ] ]
);
```

### Overriding the model per call

```php
ask_noul( $state, 'Urgent?', null, 'jev-1.13.0' );          // pin a version
evaluate( $state, $questions, 'jev-preview' );
```

## Direct client usage

For full control, use `JevClient` directly. It reads configuration from
`SettingsManager` unless you inject your own.

```php
use AiProviderForJev\Client\JevClient;

$client = new JevClient();

$response = $client->system_one(
	$state,
	[ 'q' => [ 'type' => 'noul', 'instructions' => 'Urgent?' ] ]
);

$models = $client->list_models(); // [ 'models' => [ [ 'name' => 'jev-latest', ... ] ] ]
```

On a non-2xx response the client returns a `WP_Error` with code
`jev_api_error`; the HTTP status is available via the error data:

```php
if ( is_wp_error( $response ) ) {
	$status = $response->get_error_data()['status'] ?? 0; // 401, 422, 429, 529, …
}
```

## REST API

The plugin registers an authenticated proxy that calls Jev with the site's
stored API key, so the key never reaches the browser.

| Method | Route                                       | Body                        |
| ------ | ------------------------------------------- | --------------------------- |
| `POST` | `/wp-json/ai-provider-for-jev/v1/systemone` | `{ state, questions, model? }` |
| `GET`  | `/wp-json/ai-provider-for-jev/v1/models`    | —                           |

Access requires the `manage_options` capability by default — see
[Hooks and filters](#hooks-and-filters) to change it. Requests must be
authenticated (a logged-in user with a REST nonce, or an application password).

### Request / response

```json
POST /wp-json/ai-provider-for-jev/v1/systemone
{
  "state": "Help! My payouts have been failing for 3 days.",
  "questions": {
    "is_urgent": { "type": "noul", "instructions": "Does this convey urgency?" }
  },
  "model": "jev-latest"
}
```

```json
{
  "model": "jev-1.13.0",
  "answers": {
    "is_urgent": { "type": "noul", "noul": 0.92 }
  },
  "usage": { "input_tokens": 312, "output_tokens": 48 }
}
```

On failure the proxy mirrors the upstream status (401/422/429/529) and returns a
`WP_Error`-shaped body: `{ "code": "jev_api_error", "message": "…", "data": { "status": 401 } }`.

### cURL (application password)

```bash
curl -X POST https://example.test/wp-json/ai-provider-for-jev/v1/systemone \
  -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"state":"Help!","questions":{"q":{"type":"noul","instructions":"Urgent?"}}}'
```

### JavaScript with `@wordpress/api-fetch`

`apiFetch` adds the REST nonce automatically inside wp-admin:

```js
import apiFetch from '@wordpress/api-fetch';

const result = await apiFetch( {
	path: '/ai-provider-for-jev/v1/systemone',
	method: 'POST',
	data: {
		state: 'Help!',
		questions: { is_urgent: { type: 'noul', instructions: 'Urgent?' } },
	},
} );

console.log( result.answers.is_urgent.noul );
```

## JavaScript client

The plugin ships a dependency-free ES module at
[`src/js/client.js`](../src/js/client.js) that wraps the REST proxy.

```js
import { systemOne, listModels } from '../src/js/client.js';

const result = await systemOne(
	{
		state: 'Help!',
		questions: { is_urgent: { type: 'noul', instructions: 'Urgent?' } },
		model: 'jev-latest', // optional
	},
	{ root: '/wp-json/ai-provider-for-jev/v1', nonce: window.wpApiSettings?.nonce }
);

const models = await listModels( { root: '/wp-json/ai-provider-for-jev/v1' } );
```

`fetch`, `root`, and `nonce` can be injected via the second argument (handy for
tests) or read from a global `window.aiProviderForJev` object you localize. The
module is not enqueued for you; register it from your own plugin/theme, for
example:

```php
add_action( 'wp_enqueue_scripts', function () {
	wp_register_script_module(
		'my-theme/jev',
		get_stylesheet_directory_uri() . '/js/jev.js',
		[],
		'1.0.0'
	);
	wp_enqueue_script_module( 'my-theme/jev' );

	// Expose the REST base and nonce to the module.
	wp_add_inline_script(
		'my-theme/jev',
		'window.aiProviderForJev = ' . wp_json_encode( [
			'root'  => esc_url_raw( rest_url( 'ai-provider-for-jev/v1' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		] ) . ';',
		'before'
	);
} );
```

Errors reject with an `Error` carrying `.status` and `.data`:

```js
try {
	await systemOne( { state: 's', questions: {} } );
} catch ( error ) {
	console.error( error.message, error.status, error.data );
}
```

## Hooks and filters

### `ai_provider_jev_rest_capability`

Filters the capability required to call the REST proxy. Default `manage_options`.

```php
// Allow editors to use the proxy.
add_filter( 'ai_provider_jev_rest_capability', fn() => 'edit_posts' );
```

```php
// Restrict by context — e.g. only allow it on the front end for a custom role.
add_filter( 'ai_provider_jev_rest_capability', function ( $capability ) {
	return is_admin() ? $capability : 'use_jev_api';
} );
```

### Configuring options programmatically

The three settings are plain options, so you can read or set them with core
functions and hook into their standard filters.

| Purpose      | Option name                | Constant / env fallback |
| ------------ | -------------------------- | ----------------------- |
| API key      | `ai_provider_jev_api_key`  | `TYPESAFE_API_KEY`      |
| Model        | `ai_provider_jev_model`    | `TYPESAFE_MODEL`        |
| API base URL | `ai_provider_jev_endpoint` | `TYPESAFE_ENDPOINT`     |

```php
// Set the model at activation, or in a deploy script.
update_option( 'ai_provider_jev_model', 'jev-1.13.0' );

// Force the model via a core option filter (read-only override).
add_filter( 'option_ai_provider_jev_model', fn() => 'jev-preview' );
```

> **Note:** reading `ai_provider_jev_api_key` with `get_option()` returns a
> **masked** value (bullets + last four characters). Prefer a constant/env var
> for the key, or read the real value through
> `AiProviderForJev\Settings\SettingsManager::instance()->get_api_key()`.

## Example child plugin

A complete, copy-pasteable example lives at
[`docs/examples/jev-comment-triage/`](examples/jev-comment-triage/jev-comment-triage.php).
**Jev Comment Triage** auto-moderates new comments: it asks Jev for a spam
probability (Noul) and a toxicity score (Score), then routes the comment and
stores the scores as comment meta.

It demonstrates the patterns you'll reuse in your own integrations:

- **Depend on the provider** via the `Requires Plugins: ai-provider-for-jev`
  header, and guard at runtime with `function_exists( 'AiProviderForJev\\evaluate' )`
  plus `SettingsManager::instance()->is_configured()`.
- **Batch questions** in a single `evaluate()` call.
- **Fail open** — any `WP_Error` from the API leaves WordPress's own decision
  untouched, so an outage never blocks commenting.
- **Act on the numbers** with thresholds you control in code.

The core of it:

```php
$scores = \AiProviderForJev\evaluate( $content, [
	'is_spam'  => [ 'type' => 'noul', 'instructions' => 'Is this comment spam?' ],
	'toxicity' => [
		'type'         => 'score',
		'instructions' => 'How toxic or abusive is this comment?',
		'criteria'     => [ 'Civil', 'Rude', 'Abusive or hateful' ],
	],
] );

if ( is_wp_error( $scores ) ) {
	return $approved; // fail open
}

if ( $scores['answers']['is_spam']['noul'] > 0.85 ) {
	return 'spam';
}
if ( $scores['answers']['toxicity']['score'] >= 1.5 ) {
	return '0'; // hold for moderation
}
```

To try it, copy the `jev-comment-triage` folder into `wp-content/plugins/`,
activate **AI Provider for Jev** (configured with an API key), then activate
**Jev Comment Triage**.

## Testing

PHP tests use [Pest](https://pestphp.com/) with
[Brain Monkey](https://giuseppe-mazzapica.gitbook.io/brain-monkey/); JavaScript
tests use [Vitest](https://vitest.dev/).

```bash
composer install
composer test          # Pest
npm install
npm test               # Vitest
```
