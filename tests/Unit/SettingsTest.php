<?php
/**
 * Tests for the Settings class (API key masking and sanitization).
 *
 * @package AiProviderForJev
 */

declare( strict_types=1 );

use AiProviderForJev\Settings\Settings;
use Brain\Monkey\Functions;

it( 'returns short keys unchanged when masking', function (): void {
	expect( Settings::mask_api_key( 'abcd' ) )->toBe( 'abcd' );
	expect( Settings::mask_api_key( '' ) )->toBe( '' );
} );

it( 'masks long keys with bullets plus the last four characters', function (): void {
	$masked = Settings::mask_api_key( 'abcdefghij' );

	expect( $masked )->toEndWith( 'ghij' );
	expect( str_contains( $masked, "\u{2022}" ) )->toBeTrue();
	expect( mb_strlen( $masked ) )->toBe( 10 );
} );

it( 'keeps the stored key when the submitted value is still masked', function (): void {
	Functions\when( 'get_option' )->justReturn( 'real-stored-key' );

	$result = Settings::sanitize_api_key( "\u{2022}\u{2022}\u{2022}\u{2022}6789" );

	expect( $result )->toBe( 'real-stored-key' );
} );

it( 'keeps the stored key when the submitted value is empty', function (): void {
	Functions\when( 'get_option' )->justReturn( 'real-stored-key' );

	expect( Settings::sanitize_api_key( '' ) )->toBe( 'real-stored-key' );
} );

it( 'stores a freshly entered key', function (): void {
	$result = Settings::sanitize_api_key( '  new-secret-key  ' );

	expect( $result )->toBe( 'new-secret-key' );
} );
