<?php
/**
 * HTTP client for the TypeSafe (Jev) System One API.
 *
 * @package AiProviderForJev
 */

namespace AiProviderForJev\Client;

use AiProviderForJev\Settings\SettingsManager;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Thin wrapper around the TypeSafe HTTP API.
 *
 * @phpstan-type Question array{type:string, instructions:mixed, criteria?:mixed}
 */
class JevClient {

	/**
	 * Request timeout in seconds.
	 */
	private const TIMEOUT = 30;

	private SettingsManager $settings;

	public function __construct( ?SettingsManager $settings = null ) {
		$this->settings = $settings ?? SettingsManager::instance();
	}

	/**
	 * Evaluate a state against a map of typed questions.
	 *
	 * @param string|array<mixed>|object $state     The content to evaluate.
	 * @param array<string, array<mixed>> $questions Map of question id => question definition.
	 * @param string|null                 $model     Optional model override.
	 * @return array<string, mixed>|WP_Error Decoded response body, or error.
	 */
	public function system_one( string|array|object $state, array $questions, ?string $model = null ): array|WP_Error {
		if ( empty( $questions ) ) {
			return new WP_Error(
				'jev_invalid_request',
				__( 'At least one question is required.', 'ai-provider-for-jev' )
			);
		}

		$body = [
			'state'     => $state,
			'model'     => $model ?? $this->settings->get_model(),
			'questions' => $questions,
		];

		return $this->request( 'POST', $this->settings->get_decisions_path(), $body );
	}

	/**
	 * List the models available to the configured account.
	 *
	 * Not all providers expose a models-list endpoint (OpenRouter's alpha
	 * decisions API does not), so prefer test_connection() for a health check.
	 *
	 * @return array<string, mixed>|WP_Error Decoded response body, or error.
	 */
	public function list_models(): array|WP_Error {
		return $this->request( 'GET', '/models' );
	}

	/**
	 * Verify the configured credentials/endpoint by issuing a minimal,
	 * inexpensive decision request. Works for both TypeSafe and OpenRouter.
	 *
	 * @return true|WP_Error True on success, or the API error.
	 */
	public function test_connection(): true|WP_Error {
		$result = $this->system_one(
			'ping',
			[
				'ok' => [
					'type'         => 'noul',
					'instructions' => 'Answer yes.',
					'criteria'     => [
						'true'  => 'Always',
						'false' => 'Never',
					],
				],
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Perform an authenticated request against the API.
	 *
	 * @param string                    $method HTTP method.
	 * @param string                    $path   Path relative to the API base URL.
	 * @param array<string, mixed>|null $body   Optional JSON body.
	 * @return array<string, mixed>|WP_Error
	 */
	private function request( string $method, string $path, ?array $body = null ): array|WP_Error {
		$api_key = $this->settings->get_api_key();
		if ( '' === $api_key ) {
			return new WP_Error(
				'jev_missing_api_key',
				__( 'No TypeSafe API key is configured.', 'ai-provider-for-jev' )
			);
		}

		$endpoint = $this->settings->get_endpoint();
		$url      = $endpoint . '/' . ltrim( $path, '/' );
		$headers  = [
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		];

		// OpenRouter uses these optional headers for its app leaderboards.
		if ( str_contains( $endpoint, 'openrouter.ai' ) ) {
			$headers['HTTP-Referer'] = home_url( '/' );
			$headers['X-Title']      = get_bloginfo( 'name' );
		}

		$args = [
			'method'  => $method,
			'timeout' => self::TIMEOUT,
			'headers' => $headers,
		];

		if ( null !== $body ) {
			$args['body'] = (string) wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$raw     = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			return $this->error_from_response( $code, $decoded );
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'jev_invalid_response',
				__( 'The API returned an unexpected response.', 'ai-provider-for-jev' ),
				[ 'status' => $code ]
			);
		}

		return $decoded;
	}

	/**
	 * Build a WP_Error from a non-2xx API response.
	 *
	 * @param int   $code    HTTP status code.
	 * @param mixed $decoded Decoded response body.
	 * @return WP_Error
	 */
	private function error_from_response( int $code, mixed $decoded ): WP_Error {
		$message = '';
		if ( is_array( $decoded ) ) {
			if ( isset( $decoded['error'] ) && is_string( $decoded['error'] ) ) {
				$message = $decoded['error'];
			} elseif ( isset( $decoded['message'] ) && is_string( $decoded['message'] ) ) {
				$message = $decoded['message'];
			}
		}

		if ( '' === $message ) {
			$message = match ( $code ) {
				401     => __( 'Missing or invalid API key.', 'ai-provider-for-jev' ),
				422     => __( 'The request failed validation.', 'ai-provider-for-jev' ),
				429     => __( 'Rate limit exceeded. Try again shortly.', 'ai-provider-for-jev' ),
				529     => __( 'TypeSafe is temporarily overloaded. Try again shortly.', 'ai-provider-for-jev' ),
				default => __( 'The API request failed.', 'ai-provider-for-jev' ),
			};
		}

		return new WP_Error(
			'jev_api_error',
			$message,
			[
				'status' => $code,
				'body'   => $decoded,
			]
		);
	}
}
