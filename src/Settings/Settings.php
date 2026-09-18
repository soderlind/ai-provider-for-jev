<?php
/**
 * Plugin settings registration.
 *
 * @package AiProviderForJev
 */

namespace AiProviderForJev\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Registers the plugin options with the WordPress Settings API.
 */
class Settings {

	public const SETTINGS_GROUP = 'ai_provider_jev';

	public const OPTION_API_KEY  = 'ai_provider_jev_api_key';
	public const OPTION_MODEL    = 'ai_provider_jev_model';
	public const OPTION_ENDPOINT = 'ai_provider_jev_endpoint';

	/**
	 * Bullet character used to mask stored API keys.
	 */
	private const MASK_CHAR = "\u{2022}";

	/**
	 * Register settings and the API-key masking filter.
	 */
	public static function register(): void {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_API_KEY,
			[
				'type'              => 'string',
				'label'             => __( 'TypeSafe API Key', 'ai-provider-for-jev' ),
				'description'       => __( 'API key for the TypeSafe (Jev) System One API.', 'ai-provider-for-jev' ),
				'default'           => '',
				'show_in_rest'      => false,
				'sanitize_callback' => [ __CLASS__, 'sanitize_api_key' ],
			]
		);
		add_filter( 'option_' . self::OPTION_API_KEY, [ __CLASS__, 'mask_api_key' ] );

		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_MODEL,
			[
				'type'              => 'string',
				'label'             => __( 'Model', 'ai-provider-for-jev' ),
				'description'       => __( 'Model or alias sent in the request (e.g. jev-latest).', 'ai-provider-for-jev' ),
				'default'           => '',
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
			]
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_ENDPOINT,
			[
				'type'              => 'string',
				'label'             => __( 'API Base URL', 'ai-provider-for-jev' ),
				'description'       => __( 'TypeSafe API base URL (default https://api.typesafe.ai/v1).', 'ai-provider-for-jev' ),
				'default'           => '',
				'show_in_rest'      => false,
				'sanitize_callback' => 'esc_url_raw',
			]
		);
	}

	/**
	 * Sanitize the API key, preserving the stored value when the submitted
	 * value is still the masked placeholder.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_api_key( mixed $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';

		// The form shows a masked value; if it comes back unchanged, keep the
		// real stored key instead of overwriting it with bullet characters.
		if ( '' === $value || str_contains( $value, self::MASK_CHAR ) ) {
			return self::get_real_api_key();
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Mask an API key for display: bullet characters + last 4 characters.
	 *
	 * @param mixed $key The stored API key.
	 * @return string
	 */
	public static function mask_api_key( mixed $key ): string {
		if ( ! is_string( $key ) || strlen( $key ) <= 4 ) {
			return is_string( $key ) ? $key : '';
		}

		return str_repeat( self::MASK_CHAR, min( strlen( $key ) - 4, 16 ) ) . substr( $key, -4 );
	}

	/**
	 * Read the real (unmasked) API key from the database.
	 */
	public static function get_real_api_key(): string {
		remove_filter( 'option_' . self::OPTION_API_KEY, [ __CLASS__, 'mask_api_key' ] );
		$value = get_option( self::OPTION_API_KEY, '' );
		add_filter( 'option_' . self::OPTION_API_KEY, [ __CLASS__, 'mask_api_key' ] );

		return is_string( $value ) ? $value : '';
	}
}
