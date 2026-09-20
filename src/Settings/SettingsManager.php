<?php
/**
 * Settings manager — reads option values with environment/constant fallback.
 *
 * @package AiProviderForJev
 */

namespace AiProviderForJev\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Convenience singleton resolving effective configuration values.
 */
class SettingsManager {

	public const DEFAULT_ENDPOINT       = 'https://api.typesafe.ai/v1';
	public const DEFAULT_MODEL          = 'jev-latest';
	public const DEFAULT_DECISIONS_PATH = '/systemone';

	private static ?self $instance = null;

	private function __construct() {}

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Get the real (unmasked) API key.
	 *
	 * Fallback chain: option → TYPESAFE_API_KEY env/constant → ''.
	 */
	public function get_api_key(): string {
		$key = Settings::get_real_api_key();
		if ( '' !== $key ) {
			return $key;
		}

		$key = $this->resolve_env( 'TYPESAFE_API_KEY' );
		if ( '' !== $key ) {
			return $key;
		}

		// Support an OpenRouter-issued key when the endpoint points at OpenRouter.
		return $this->resolve_env( 'OPENROUTER_API_KEY' );
	}

	/**
	 * Get the path appended to the base URL for decision requests.
	 *
	 * Fallback chain: option → TYPESAFE_DECISIONS_PATH env/constant → /systemone.
	 */
	public function get_decisions_path(): string {
		$value = get_option( Settings::OPTION_DECISIONS_PATH, '' );
		if ( is_string( $value ) && '' !== $value ) {
			return '/' . ltrim( $value, '/' );
		}

		$env = $this->resolve_env( 'TYPESAFE_DECISIONS_PATH' );
		if ( '' !== $env ) {
			return '/' . ltrim( $env, '/' );
		}

		return self::DEFAULT_DECISIONS_PATH;
	}

	/**
	 * Get the model/alias.
	 *
	 * Fallback chain: option → TYPESAFE_MODEL env/constant → jev-latest.
	 */
	public function get_model(): string {
		$value = get_option( Settings::OPTION_MODEL, '' );
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}

		$env = $this->resolve_env( 'TYPESAFE_MODEL' );
		if ( '' !== $env ) {
			return $env;
		}

		return self::DEFAULT_MODEL;
	}

	/**
	 * Get the API base URL.
	 *
	 * Fallback chain: option → TYPESAFE_ENDPOINT env/constant → default.
	 */
	public function get_endpoint(): string {
		$value = get_option( Settings::OPTION_ENDPOINT, '' );
		if ( is_string( $value ) && '' !== $value ) {
			return untrailingslashit( $value );
		}

		$env = $this->resolve_env( 'TYPESAFE_ENDPOINT' );
		if ( '' !== $env ) {
			return untrailingslashit( $env );
		}

		return self::DEFAULT_ENDPOINT;
	}

	/**
	 * True when an API key is configured (option or environment).
	 */
	public function is_configured(): bool {
		return '' !== $this->get_api_key();
	}

	/**
	 * Read an environment variable or wp-config.php constant.
	 *
	 * @param string $name Variable/constant name.
	 * @return string Value, or '' when unset.
	 */
	public function resolve_env( string $name ): string {
		$value = getenv( $name );
		if ( false !== $value && '' !== $value ) {
			return (string) $value;
		}

		if ( defined( $name ) ) {
			$const = constant( $name );
			if ( is_string( $const ) && '' !== $const ) {
				return $const;
			}
		}

		return '';
	}
}
