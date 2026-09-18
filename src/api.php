<?php
/**
 * Public helper API for other plugins and themes.
 *
 * These functions wrap {@see \AiProviderForJev\Client\JevClient} to make the
 * three Jev primitives — noul, choice, and score — easy to call from code.
 *
 * @package AiProviderForJev
 */

namespace AiProviderForJev;

use AiProviderForJev\Client\JevClient;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Evaluate a state against a map of typed questions.
 *
 * @param string|array<mixed>|object  $state     The content to evaluate.
 * @param array<string, array<mixed>> $questions Map of question id => definition.
 * @param string|null                 $model     Optional model override.
 * @return array<string, mixed>|WP_Error The full API response, or error.
 */
function evaluate( string|array|object $state, array $questions, ?string $model = null ): array|WP_Error {
	return ( new JevClient() )->system_one( $state, $questions, $model );
}

/**
 * Ask a single Noul (yes/no) question and return the probability of "yes".
 *
 * @param string|array<mixed>|object $state        The content to evaluate.
 * @param string                     $instructions The yes/no question.
 * @param array{true?:string,false?:string}|null $criteria Optional meaning of yes/no.
 * @param string|null                $model        Optional model override.
 * @return float|WP_Error Probability (0..1), or error.
 */
function ask_noul( string|array|object $state, string $instructions, ?array $criteria = null, ?string $model = null ): float|WP_Error {
	$question = [
		'type'         => 'noul',
		'instructions' => $instructions,
	];
	if ( null !== $criteria ) {
		$question['criteria'] = $criteria;
	}

	$answer = single_answer( $state, $question, $model );
	if ( is_wp_error( $answer ) ) {
		return $answer;
	}

	return isset( $answer['noul'] ) ? (float) $answer['noul'] : new WP_Error(
		'jev_invalid_response',
		__( 'The answer did not contain a noul value.', 'ai-provider-for-jev' )
	);
}

/**
 * Ask a single Choice question and return the answer.
 *
 * @param string|array<mixed>|object   $state        The content to evaluate.
 * @param string                       $instructions What to decide.
 * @param array<string, string|null>   $criteria     Option => description map.
 * @param string|null                  $model        Optional model override.
 * @return array{choice:string, probabilities:array<string,float>, confidence:float}|WP_Error
 */
function ask_choice( string|array|object $state, string $instructions, array $criteria, ?string $model = null ): array|WP_Error {
	return single_answer(
		$state,
		[
			'type'         => 'choice',
			'instructions' => $instructions,
			'criteria'     => $criteria,
		],
		$model
	);
}

/**
 * Ask a single Score question and return the answer.
 *
 * @param string|array<mixed>|object $state        The content to evaluate.
 * @param string                     $instructions What to rate.
 * @param list<string>               $criteria     Ordered level descriptions (>= 2).
 * @param string|null                $model        Optional model override.
 * @return array{score:float, legend:array<string,string>, probabilities:array<string,float>, confidence:float}|WP_Error
 */
function ask_score( string|array|object $state, string $instructions, array $criteria, ?string $model = null ): array|WP_Error {
	return single_answer(
		$state,
		[
			'type'         => 'score',
			'instructions' => $instructions,
			'criteria'     => $criteria,
		],
		$model
	);
}

/**
 * Run a single question and return just its answer payload.
 *
 * @param string|array<mixed>|object $state    The content to evaluate.
 * @param array<string, mixed>       $question A single question definition.
 * @param string|null                $model    Optional model override.
 * @return array<string, mixed>|WP_Error
 */
function single_answer( string|array|object $state, array $question, ?string $model = null ): array|WP_Error {
	$response = evaluate( $state, [ 'q' => $question ], $model );
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	if ( ! isset( $response['answers']['q'] ) || ! is_array( $response['answers']['q'] ) ) {
		return new WP_Error(
			'jev_invalid_response',
			__( 'The API response did not contain an answer.', 'ai-provider-for-jev' )
		);
	}

	return $response['answers']['q'];
}
