<?php
/**
 * Tests for the public helper functions in src/api.php.
 *
 * @package AiProviderForJev
 */

declare( strict_types=1 );

use AiProviderForJev\Settings\Settings;
use Brain\Monkey\Functions;

use function AiProviderForJev\ask_choice;
use function AiProviderForJev\ask_noul;
use function AiProviderForJev\ask_score;

/**
 * Stub options and a canned systemone response body.
 *
 * @param array  $answers Answers map to return under "answers".
 * @param int    $code    HTTP status code.
 */
function stub_systemone( array $answers, int $code = 200 ): void {
	Functions\when( 'get_option' )->alias(
		static fn( $name, $default = '' ) => Settings::OPTION_API_KEY === $name ? 'secret' : $default
	);
	Functions\when( 'wp_remote_request' )->justReturn(
		[
			'response' => [ 'code' => $code ],
			'body'     => json_encode( [ 'model' => 'jev-1.13.0', 'answers' => $answers ] ),
		]
	);
	Functions\when( 'wp_remote_retrieve_response_code' )->alias(
		static fn( $response ) => $response['response']['code']
	);
	Functions\when( 'wp_remote_retrieve_body' )->alias(
		static fn( $response ) => $response['body']
	);
}

it( 'ask_noul returns the probability as a float', function (): void {
	stub_systemone( [ 'q' => [ 'type' => 'noul', 'noul' => 0.75 ] ] );

	expect( ask_noul( 'ticket', 'Is this urgent?' ) )->toBe( 0.75 );
} );

it( 'ask_choice returns the answer payload', function (): void {
	stub_systemone(
		[
			'q' => [
				'type'          => 'choice',
				'choice'        => 'billing',
				'probabilities' => [ 'billing' => 0.8, 'sales' => 0.2 ],
				'confidence'    => 0.6,
			],
		]
	);

	$answer = ask_choice( 'ticket', 'Which team?', [ 'billing' => null, 'sales' => null ] );

	expect( $answer['choice'] )->toBe( 'billing' );
	expect( $answer['probabilities']['billing'] )->toBe( 0.8 );
} );

it( 'ask_score returns the answer payload', function (): void {
	stub_systemone(
		[
			'q' => [
				'type'       => 'score',
				'score'      => 1.6,
				'legend'     => [ '0' => 'Calm', '1' => 'Frustrated', '2' => 'Very angry' ],
				'confidence' => 0.7,
			],
		]
	);

	$answer = ask_score( 'ticket', 'How frustrated?', [ 'Calm', 'Frustrated', 'Very angry' ] );

	expect( $answer['score'] )->toBe( 1.6 );
} );

it( 'propagates a WP_Error from the API', function (): void {
	stub_systemone( [], 401 );

	$result = ask_noul( 'ticket', 'Is this urgent?' );

	expect( $result )->toBeInstanceOf( \WP_Error::class );
} );
