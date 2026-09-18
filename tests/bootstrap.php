<?php
/**
 * PHPUnit/Pest bootstrap.
 *
 * Defines the minimal WordPress surface the plugin needs (ABSPATH and a
 * WP_Error stand-in), loads the Composer autoloader, then loads the helper
 * functions from src/api.php after ABSPATH exists so their guard passes.
 *
 * @package AiProviderForJev
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stand-in for unit tests.
	 */
	class WP_Error {

		/** @var array<string, list<string>> */
		protected array $errors = [];

		/** @var array<string, mixed> */
		protected array $error_data = [];

		public function __construct( string $code = '', string $message = '', mixed $data = '' ) {
			if ( '' !== $code ) {
				$this->errors[ $code ][] = $message;
				if ( '' !== $data ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		public function get_error_code(): string {
			return (string) ( array_key_first( $this->errors ) ?? '' );
		}

		public function get_error_message( string $code = '' ): string {
			$code = '' !== $code ? $code : $this->get_error_code();
			return $this->errors[ $code ][0] ?? '';
		}

		public function get_error_data( string $code = '' ): mixed {
			$code = '' !== $code ? $code : $this->get_error_code();
			return $this->error_data[ $code ] ?? null;
		}

		public function add_data( mixed $data, string $code = '' ): void {
			$code = '' !== $code ? $code : $this->get_error_code();
			$this->error_data[ $code ] = $data;
		}
	}
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Helper functions are guarded by ABSPATH, so load them now that it exists.
require_once dirname( __DIR__ ) . '/src/api.php';
