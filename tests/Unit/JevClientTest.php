<?php
/**
 * Tests for the JevClient HTTP wrapper.
 *
 * @package AiProviderForJev
 */

declare( strict_types=1 );

use AiProviderForJev\Client\JevClient;
use AiProviderForJev\Settings\Settings;
use Brain\Monkey\Functions;

/**
 * Stub get_option so the API key resolves and model/endpoint use defaults.
 *
 * @param string $api_key API key to return.
 */
function stub_options( string $api_key = 'secret-abcd' ): void {
	Functions\when( 'get_option' )->alias(
		static fn( $name, $default = '' ) => Settings::OPTION_API_KEY === $name ? $api_key : $default
	);
}

/**
 * Stub the HTTP transport to return a canned response.
 *
 * @param int    $code    HTTP status code.
 * @param array  $body    Response body (encoded to JSON).
 * @param mixed  $capture Reference that receives the request arguments.
 */
function stub_http( int $code, array $body, &$capture = null ): void {
	Functions\when( 'wp_remote_request' )->alias(
		function ( $url, $args ) use ( $code, $body, &$capture ) {
			$capture = [ 'url' => $url, 'args' => $args ];
			return [ 'response' => [ 'code' => $code ], 'body' => json_encode( $body ) ];
		}
	);
	Functions\when( 'wp_remote_retrieve_response_code' )->alias(
		static fn( $response ) => $response['response']['code']
	);
	Functions\when( 'wp_remote_retrieve_body' )->alias(
		static fn( $response ) => $response['body']
	);
}

it( 'sends a well-formed authenticated request and decodes the response', function (): void {
	stub_options();
	stub_http(
		200,
		[
			'model'   => 'jev-1.13.0',
			'answers' => [ 'q' => [ 'type' => 'noul', 'noul' => 0.9 ] ],
			'usage'   => [ 'input_tokens' => 10, 'output_tokens' => 2 ],
		],
		$capture
	);

	$result = ( new JevClient() )->system_one(
		'ticket text',
		[ 'q' => [ 'type' => 'noul', 'instructions' => 'Urgent?' ] ]
	);

	expect( $result['answers']['q']['noul'] )->toBe( 0.9 );
	expect( $capture['url'] )->toBe( 'https://api.typesafe.ai/v1/systemone' );
	expect( $capture['args']['headers']['Authorization'] )->toBe( 'Bearer secret-abcd' );

	$sent = json_decode( $capture['args']['body'], true );
	expect( $sent['model'] )->toBe( 'jev-latest' );
	expect( $sent['state'] )->toBe( 'ticket text' );
	expect( $sent['questions'] )->toHaveKey( 'q' );
} );

it( 'rejects an empty questions map before making a request', function (): void {
	stub_options();

	$result = ( new JevClient() )->system_one( 'x', [] );

	expect( $result )->toBeInstanceOf( \WP_Error::class );
	expect( $result->get_error_code() )->toBe( 'jev_invalid_request' );
} );

it( 'returns an error when no API key is configured', function (): void {
	stub_options( '' );

	$result = ( new JevClient() )->system_one(
		'x',
		[ 'q' => [ 'type' => 'noul', 'instructions' => 'Urgent?' ] ]
	);

	expect( $result )->toBeInstanceOf( \WP_Error::class );
	expect( $result->get_error_code() )->toBe( 'jev_missing_api_key' );
} );

it( 'maps a non-2xx response to a WP_Error carrying the status', function (): void {
	stub_options();
	stub_http( 401, [ 'error' => 'Invalid API key' ] );

	$result = ( new JevClient() )->system_one(
		'x',
		[ 'q' => [ 'type' => 'noul', 'instructions' => 'Urgent?' ] ]
	);

	expect( $result )->toBeInstanceOf( \WP_Error::class );
	expect( $result->get_error_code() )->toBe( 'jev_api_error' );
	expect( $result->get_error_message() )->toBe( 'Invalid API key' );
	expect( $result->get_error_data()['status'] )->toBe( 401 );
} );

it( 'lists models', function (): void {
	stub_options();
	stub_http( 200, [ 'models' => [ [ 'name' => 'jev-latest' ] ] ], $capture );

	$result = ( new JevClient() )->list_models();

	expect( $result['models'][0]['name'] )->toBe( 'jev-latest' );
	expect( $capture['url'] )->toBe( 'https://api.typesafe.ai/v1/models' );
} );
