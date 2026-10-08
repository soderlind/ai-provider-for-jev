<?php
/**
 * Plugin Name: AI Provider for Jev
 * Plugin URI:  https://github.com/soderlind/ai-provider-for-jev
 * Description: Connect WordPress to TypeSafe's Jev "System One" model for structured decisions (choice, score, noul).
 * Requires at least: 6.8
 * Requires PHP: 8.3
 * Version: 1.0.0
 * Author: Per Søderlind
 * Author URI: https://soderlind.no/
 * License: GPL-2.0-or-later
 * Text Domain: ai-provider-for-jev
 *
 * @package AiProviderForJev
 */

namespace AiProviderForJev;

use AiProviderForJev\Admin\SettingsPage;
use AiProviderForJev\Rest\SystemOneController;
use AiProviderForJev\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

$autoload = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $autoload ) ) {
	require_once $autoload;
}

if ( class_exists( \Soderlind\WordPress\GitHubUpdater::class ) ) {
	\Soderlind\WordPress\GitHubUpdater::init(
		github_url:   'https://github.com/soderlind/ai-provider-for-jev',
		plugin_file:  __FILE__,
		plugin_slug:  'ai-provider-for-jev',
		name_regex:   '/ai-provider-for-jev\.zip/',
		branch:       'main',
		check_period: 6,
	);
}

define( 'AI_PROVIDER_FOR_JEV_VERSION', '1.0.0' );
define( 'AI_PROVIDER_FOR_JEV_FILE', __FILE__ );

require_once __DIR__ . '/src/autoload.php';
require_once __DIR__ . '/src/api.php';

/**
 * Register plugin settings.
 */
add_action( 'init', [ Settings::class, 'register' ] );

/**
 * Register the admin settings page.
 */
add_action( 'admin_menu', [ SettingsPage::class, 'register' ] );

/**
 * Register the REST proxy endpoints.
 */
add_action( 'rest_api_init', [ SystemOneController::class, 'register' ] );
