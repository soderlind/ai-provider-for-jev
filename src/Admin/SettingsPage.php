<?php
/**
 * Admin settings page.
 *
 * @package AiProviderForJev
 */

namespace AiProviderForJev\Admin;

use AiProviderForJev\Client\JevClient;
use AiProviderForJev\Settings\Settings;
use AiProviderForJev\Settings\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Renders the Settings → Jev (TypeSafe) page.
 */
class SettingsPage {

	private const MENU_SLUG = 'ai-provider-for-jev';

	/**
	 * Register the options page.
	 */
	public static function register(): void {
		add_options_page(
			__( 'AI Provider for Jev', 'ai-provider-for-jev' ),
			__( 'Jev (TypeSafe)', 'ai-provider-for-jev' ),
			'manage_options',
			self::MENU_SLUG,
			[ __CLASS__, 'render' ]
		);
	}

	/**
	 * Render the settings form.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = SettingsManager::instance();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'AI Provider for Jev', 'ai-provider-for-jev' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: 1: TypeSafe docs link, 2: OpenRouter docs link. */
					esc_html__( 'Connect WordPress to the Jev System One decision model, via TypeSafe (%1$s) or OpenRouter (%2$s).', 'ai-provider-for-jev' ),
					'<a href="https://docs.typesafe.ai/introduction" target="_blank" rel="noreferrer noopener">' . esc_html__( 'docs', 'ai-provider-for-jev' ) . '</a>',
					'<a href="https://openrouter.ai/~typesafe/jev-latest" target="_blank" rel="noreferrer noopener">' . esc_html__( 'docs', 'ai-provider-for-jev' ) . '</a>'
				);
				?>
			</p>
			<p class="description">
				<?php
				esc_html_e( 'For OpenRouter, set Base URL to https://openrouter.ai/api/alpha, Decisions Path to /decisions, and Model to ~typesafe/jev-latest.', 'ai-provider-for-jev' );
				?>
			</p>

			<?php self::maybe_render_connection_notice( $settings ); ?>

			<form action="options.php" method="post">
				<?php settings_fields( Settings::SETTINGS_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( Settings::OPTION_API_KEY ); ?>">
								<?php echo esc_html__( 'API Key', 'ai-provider-for-jev' ); ?>
							</label>
						</th>
						<td>
							<input
								type="password"
								class="regular-text"
								autocomplete="off"
								id="<?php echo esc_attr( Settings::OPTION_API_KEY ); ?>"
								name="<?php echo esc_attr( Settings::OPTION_API_KEY ); ?>"
								value="<?php echo esc_attr( get_option( Settings::OPTION_API_KEY, '' ) ); ?>"
							/>
							<p class="description">
								<?php echo esc_html__( 'Falls back to the TYPESAFE_API_KEY constant or environment variable.', 'ai-provider-for-jev' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( Settings::OPTION_MODEL ); ?>">
								<?php echo esc_html__( 'Model', 'ai-provider-for-jev' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								class="regular-text"
								id="<?php echo esc_attr( Settings::OPTION_MODEL ); ?>"
								name="<?php echo esc_attr( Settings::OPTION_MODEL ); ?>"
								value="<?php echo esc_attr( get_option( Settings::OPTION_MODEL, '' ) ); ?>"
								placeholder="<?php echo esc_attr( SettingsManager::DEFAULT_MODEL ); ?>"
							/>
							<p class="description">
								<?php echo esc_html__( 'e.g. jev-latest, jev-preview, or a pinned version such as jev-1.13.0.', 'ai-provider-for-jev' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( Settings::OPTION_ENDPOINT ); ?>">
								<?php echo esc_html__( 'API Base URL', 'ai-provider-for-jev' ); ?>
							</label>
						</th>
						<td>
							<input
								type="url"
								class="regular-text"
								id="<?php echo esc_attr( Settings::OPTION_ENDPOINT ); ?>"
								name="<?php echo esc_attr( Settings::OPTION_ENDPOINT ); ?>"
								value="<?php echo esc_attr( get_option( Settings::OPTION_ENDPOINT, '' ) ); ?>"
								placeholder="<?php echo esc_attr( SettingsManager::DEFAULT_ENDPOINT ); ?>"
							/>
							<p class="description">
								<?php echo esc_html__( 'Leave blank to use the default TypeSafe endpoint.', 'ai-provider-for-jev' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( Settings::OPTION_DECISIONS_PATH ); ?>">
								<?php echo esc_html__( 'Decisions Path', 'ai-provider-for-jev' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								class="regular-text"
								id="<?php echo esc_attr( Settings::OPTION_DECISIONS_PATH ); ?>"
								name="<?php echo esc_attr( Settings::OPTION_DECISIONS_PATH ); ?>"
								value="<?php echo esc_attr( get_option( Settings::OPTION_DECISIONS_PATH, '' ) ); ?>"
								placeholder="<?php echo esc_attr( SettingsManager::DEFAULT_DECISIONS_PATH ); ?>"
							/>
							<p class="description">
								<?php echo esc_html__( 'Use /systemone for TypeSafe or /decisions for OpenRouter.', 'ai-provider-for-jev' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<?php if ( $settings->is_configured() ) : ?>
				<hr />
				<a
					class="button button-secondary"
					href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'jev_test', '1' ), 'jev_test_connection' ) ); ?>"
				>
					<?php echo esc_html__( 'Test connection', 'ai-provider-for-jev' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Run and render a connection test when requested via the test link.
	 *
	 * @param SettingsManager $settings Settings manager.
	 */
	private static function maybe_render_connection_notice( SettingsManager $settings ): void {
		if ( '1' !== ( $_GET['jev_test'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'jev_test_connection' ) ) {
			return;
		}

		if ( ! $settings->is_configured() ) {
			return;
		}

		$result = ( new JevClient( $settings ) )->test_connection();

		if ( is_wp_error( $result ) ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: error message. */
						__( 'Connection failed: %s', 'ai-provider-for-jev' ),
						$result->get_error_message()
					)
				)
			);
			return;
		}

		printf(
			'<div class="notice notice-success"><p>%s</p></div>',
			esc_html__( 'Connection successful. Your API key is valid.', 'ai-provider-for-jev' )
		);
	}
}
