<?php
/**
 * Uninstall cleanup.
 *
 * @package AiProviderForJev
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

foreach ( [ 'ai_provider_jev_api_key', 'ai_provider_jev_model', 'ai_provider_jev_endpoint' ] as $option ) {
	delete_option( $option );
}
