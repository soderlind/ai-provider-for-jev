<?php
/**
 * REST proxy for the TypeSafe (Jev) System One API.
 *
 * @package AiProviderForJev
 */

namespace AiProviderForJev\Rest;

use AiProviderForJev\Client\JevClient;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Exposes the Jev evaluation and model-list endpoints to authenticated users.
 */
class SystemOneController {

	public const NAMESPACE = 'ai-provider-for-jev/v1';

	/**
	 * Register the REST routes.
	 */
	public static function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/systemone',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'evaluate' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'state'     => [
						'required' => true,
					],
					'questions' => [
						'required' => true,
						'type'     => 'object',
					],
					'model'     => [
						'type' => 'string',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/models',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'list_models' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);
	}

	/**
	 * Capability gate for the proxy endpoints.
	 *
	 * The proxy uses the site's stored API key, so access is restricted to a
	 * privileged capability by default. Filter to widen or narrow access.
	 */
	public static function check_permission(): bool {
		/**
		 * Filters the capability required to call the Jev REST proxy.
		 *
		 * @param string $capability Default 'manage_options'.
		 */
		$capability = (string) apply_filters( 'ai_provider_jev_rest_capability', 'manage_options' );

		return current_user_can( $capability );
	}

	/**
	 * Handle POST /systemone.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function evaluate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$questions = $request->get_param( 'questions' );
		if ( ! is_array( $questions ) ) {
			return new WP_Error(
				'jev_invalid_request',
				__( 'The "questions" parameter must be an object.', 'ai-provider-for-jev' ),
				[ 'status' => 400 ]
			);
		}

		$model = $request->get_param( 'model' );
		$model = is_string( $model ) && '' !== $model ? $model : null;

		$result = ( new JevClient() )->system_one( $request->get_param( 'state' ), $questions, $model );

		return self::to_response( $result );
	}

	/**
	 * Handle GET /models.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function list_models(): WP_REST_Response|WP_Error {
		return self::to_response( ( new JevClient() )->list_models() );
	}

	/**
	 * Convert a client result into a REST response, preserving upstream status.
	 *
	 * @param array<string, mixed>|WP_Error $result Client result.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function to_response( array|WP_Error $result ): WP_REST_Response|WP_Error {
		if ( is_wp_error( $result ) ) {
			$status = (int) ( $result->get_error_data()['status'] ?? 502 );
			$result->add_data( [ 'status' => $status ] );
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}
}
