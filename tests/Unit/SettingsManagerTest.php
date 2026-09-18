<?php
/**
 * Tests for the SettingsManager option/environment fallback chain.
 *
 * @package AiProviderForJev
 */

declare( strict_types=1 );

use AiProviderForJev\Settings\Settings;
use AiProviderForJev\Settings\SettingsManager;
use Brain\Monkey\Functions;

afterEach( function (): void {
	putenv( 'TYPESAFE_MODEL' );
	putenv( 'TYPESAFE_ENDPOINT' );
	putenv( 'TYPESAFE_API_KEY' );
} );

it( 'returns the default model when nothing is configured', function (): void {
	Functions\when( 'get_option' )->justReturn( '' );

	expect( SettingsManager::instance()->get_model() )->toBe( 'jev-latest' );
} );

it( 'reads the model from the option', function (): void {
	Functions\when( 'get_option' )->alias(
		static fn( $name, $default = '' ) => Settings::OPTION_MODEL === $name ? 'jev-1.13.0' : $default
	);

	expect( SettingsManager::instance()->get_model() )->toBe( 'jev-1.13.0' );
} );

it( 'falls back to the environment for the model', function (): void {
	Functions\when( 'get_option' )->justReturn( '' );
	putenv( 'TYPESAFE_MODEL=jev-preview' );

	expect( SettingsManager::instance()->get_model() )->toBe( 'jev-preview' );
} );

it( 'returns the default endpoint when nothing is configured', function (): void {
	Functions\when( 'get_option' )->justReturn( '' );

	expect( SettingsManager::instance()->get_endpoint() )->toBe( 'https://api.typesafe.ai/v1' );
} );

it( 'trims a trailing slash from the configured endpoint', function (): void {
	Functions\when( 'get_option' )->alias(
		static fn( $name, $default = '' ) => Settings::OPTION_ENDPOINT === $name ? 'https://example.test/v1/' : $default
	);

	expect( SettingsManager::instance()->get_endpoint() )->toBe( 'https://example.test/v1' );
} );

it( 'falls back to the environment for the API key', function (): void {
	Functions\when( 'get_option' )->justReturn( '' );
	putenv( 'TYPESAFE_API_KEY=env-secret' );

	expect( SettingsManager::instance()->get_api_key() )->toBe( 'env-secret' );
	expect( SettingsManager::instance()->is_configured() )->toBeTrue();
} );
