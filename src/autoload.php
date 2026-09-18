<?php
/**
 * PSR-4 autoloader for the AiProviderForJev namespace.
 *
 * @package AiProviderForJev
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'AiProviderForJev\\';

		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);
