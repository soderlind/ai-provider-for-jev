<?php
/**
 * Plugin Name: Jev Comment Triage (example)
 * Description: Example child plugin — uses AI Provider for Jev to auto-moderate comments (spam + toxicity) and store the scores as comment meta.
 * Requires Plugins: ai-provider-for-jev
 * Requires PHP: 8.3
 * Version: 1.0.0
 * License: GPL-2.0-or-later
 *
 * This is a documentation example. Copy it into wp-content/plugins/ to try it.
 *
 * @package JevCommentTriage
 */

namespace JevCommentTriage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const META_KEY = '_jev_triage';

/**
 * True when the AI Provider for Jev helper API is available and configured.
 */
function provider_ready(): bool {
	if ( ! function_exists( 'AiProviderForJev\\evaluate' ) ) {
		return false;
	}

	return \AiProviderForJev\Settings\SettingsManager::instance()->is_configured();
}

/**
 * Ask Jev about a comment: spam probability and a toxicity score.
 *
 * @param string $content Comment text.
 * @return array{is_spam:float, toxicity:float}|\WP_Error
 */
function evaluate_comment( string $content ): array|\WP_Error {
	$response = \AiProviderForJev\evaluate(
		$content,
		[
			'is_spam'  => [
				'type'         => 'noul',
				'instructions' => 'Is this comment spam, promotional, or link-farming?',
			],
			'toxicity' => [
				'type'         => 'score',
				'instructions' => 'How toxic or abusive is this comment?',
				'criteria'     => [ 'Civil', 'Rude', 'Abusive or hateful' ],
			],
		]
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	return [
		'is_spam'  => (float) ( $response['answers']['is_spam']['noul'] ?? 0.0 ),
		'toxicity' => (float) ( $response['answers']['toxicity']['score'] ?? 0.0 ),
	];
}

/**
 * Decide a comment's approval status from Jev's assessment.
 *
 * Fails open: any provider/API problem leaves WordPress's own decision intact.
 *
 * @param int|string|\WP_Error $approved    Current approval status.
 * @param array                $commentdata Comment data.
 * @return int|string|\WP_Error
 */
function moderate( $approved, array $commentdata ) {
	if ( is_wp_error( $approved ) || ! provider_ready() ) {
		return $approved;
	}

	$content = trim( (string) ( $commentdata['comment_content'] ?? '' ) );
	if ( '' === $content ) {
		return $approved;
	}

	$scores = evaluate_comment( $content );
	if ( is_wp_error( $scores ) ) {
		return $approved;
	}

	// Remember the scores so the comment_post hook can save them as meta.
	$GLOBALS['jev_triage_last'] = $scores;

	if ( $scores['is_spam'] > 0.85 ) {
		return 'spam';
	}

	if ( $scores['toxicity'] >= 1.5 ) {
		return '0'; // Hold for manual moderation.
	}

	return $approved;
}
add_filter( 'pre_comment_approved', __NAMESPACE__ . '\\moderate', 10, 2 );

/**
 * Persist the most recent triage scores against the inserted comment.
 *
 * @param int $comment_id New comment ID.
 */
function store_scores( int $comment_id ): void {
	if ( empty( $GLOBALS['jev_triage_last'] ) ) {
		return;
	}

	add_comment_meta( $comment_id, META_KEY, $GLOBALS['jev_triage_last'], true );
	unset( $GLOBALS['jev_triage_last'] );
}
add_action( 'comment_post', __NAMESPACE__ . '\\store_scores' );

/**
 * Show the stored scores as a column in the admin comments list.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function add_column( array $columns ): array {
	$columns['jev_triage'] = __( 'Jev', 'jev-comment-triage' );
	return $columns;
}
add_filter( 'manage_edit-comments_columns', __NAMESPACE__ . '\\add_column' );

/**
 * Render the triage column.
 *
 * @param string $column     Column id.
 * @param int    $comment_id Comment id.
 */
function render_column( string $column, int $comment_id ): void {
	if ( 'jev_triage' !== $column ) {
		return;
	}

	$scores = get_comment_meta( $comment_id, META_KEY, true );
	if ( ! is_array( $scores ) ) {
		echo '—';
		return;
	}

	printf(
		'spam %s · toxicity %s',
		esc_html( number_format( (float) $scores['is_spam'], 2 ) ),
		esc_html( number_format( (float) $scores['toxicity'], 2 ) )
	);
}
add_action( 'manage_comments_custom_column', __NAMESPACE__ . '\\render_column', 10, 2 );
