=== AI Provider for Jev ===
Contributors: PerS
Tags: ai, typesafe, jev, classification, structured-output
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress to TypeSafe's Jev "System One" model for structured decisions: choice, score, and noul.

== Description ==

Jev is TypeSafe's flagship System One model. Instead of generating text, it evaluates a *state* against typed *questions* and returns structured answers your code can use directly:

* **Choice** — pick one option from a set, with a probability distribution and confidence.
* **Score** — rate the state on an ordered rubric, with per-level probabilities and confidence.
* **Noul** — a yes/no question, returned as a probability from 0 to 1.

This plugin provides:

* A settings page (Settings → Jev (TypeSafe)) for your API key, model, and endpoint.
* A PHP helper API for calling Jev from other plugins and themes.
* An authenticated REST proxy (`ai-provider-for-jev/v1/systemone` and `/models`).

= Configuration =

Set your API key on the settings page, or define the `TYPESAFE_API_KEY` constant in `wp-config.php`. The model (`TYPESAFE_MODEL`) and base URL (`TYPESAFE_ENDPOINT`) can also come from constants or environment variables.

= PHP usage =

`
use function AiProviderForJev\ask_noul;
use function AiProviderForJev\ask_choice;
use function AiProviderForJev\ask_score;

$urgent = ask_noul( $ticket, 'Does this message express urgency?' );

$dept = ask_choice( $ticket, 'Which team should handle this?', [
	'billing'   => 'Payment or subscription issues',
	'technical' => 'Bugs or integration problems',
	'sales'     => 'Pricing or account questions',
] );

$frustration = ask_score( $ticket, 'How frustrated is the customer?', [
	'Calm', 'Frustrated', 'Very angry',
] );
`

Each helper returns a `WP_Error` on failure.

== Changelog ==

= 0.1.0 =
* Initial release: settings page, PHP helper API, and REST proxy for the Jev System One API.
