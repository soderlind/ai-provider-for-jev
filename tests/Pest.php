<?php
/**
 * Pest configuration: Brain Monkey lifecycle and shared WordPress stubs.
 *
 * @package AiProviderForJev
 */

declare( strict_types=1 );

use Brain\Monkey;
use Brain\Monkey\Functions;

uses()
	->beforeEach(
		function (): void {
			Monkey\setUp();

			// Deterministic pass-through stubs used across the suite.
			Functions\when( '__' )->returnArg();
			Functions\when( 'sanitize_text_field' )->alias(
				static fn( $value ) => is_string( $value ) ? trim( $value ) : ''
			);
			Functions\when( 'wp_json_encode' )->alias(
				static fn( $data, $options = 0, $depth = 512 ) => json_encode( $data, $options, $depth )
			);
			Functions\when( 'untrailingslashit' )->alias(
				static fn( $value ) => rtrim( (string) $value, '/' )
			);
			Functions\when( 'add_filter' )->justReturn( true );
			Functions\when( 'remove_filter' )->justReturn( true );
			Functions\when( 'is_wp_error' )->alias(
				static fn( $thing ) => $thing instanceof \WP_Error
			);
		}
	)
	->afterEach(
		function (): void {
			Monkey\tearDown();
		}
	)
	->in( 'Unit' );
