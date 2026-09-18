/**
 * Browser client for the AI Provider for Jev REST proxy.
 *
 * Framework-agnostic; `fetch`, base URL, and nonce can be injected for testing
 * or supplied via a localized `window.aiProviderForJev` object at runtime.
 *
 * @packageDocumentation
 */

const DEFAULT_BASE = '/wp-json/ai-provider-for-jev/v1';

/**
 * Resolve effective request configuration.
 *
 * @param {object} [options]
 * @param {typeof fetch} [options.fetch] Fetch implementation.
 * @param {string} [options.root] REST base URL.
 * @param {string} [options.nonce] WordPress REST nonce.
 * @returns {{ fetchImpl: typeof fetch, base: string, nonce: string }}
 */
function resolveConfig( options = {} ) {
	const settings =
		( typeof window !== 'undefined' && window.aiProviderForJev ) || {};

	const fetchImpl =
		options.fetch ||
		( typeof window !== 'undefined' && window.fetch
			? window.fetch.bind( window )
			: globalThis.fetch );

	return {
		fetchImpl,
		base: options.root || settings.root || DEFAULT_BASE,
		nonce: options.nonce || settings.nonce || '',
	};
}

/**
 * Perform a request against the REST proxy.
 *
 * @param {string} path Path relative to the REST base.
 * @param {RequestInit} init Fetch init.
 * @param {object} [options] Config overrides.
 * @returns {Promise<any>} Parsed JSON body.
 */
async function request( path, init, options = {} ) {
	const { fetchImpl, base, nonce } = resolveConfig( options );

	if ( typeof fetchImpl !== 'function' ) {
		throw new Error( 'No fetch implementation available.' );
	}

	const headers = {
		'Content-Type': 'application/json',
		Accept: 'application/json',
		...( init.headers || {} ),
	};

	if ( nonce ) {
		headers[ 'X-WP-Nonce' ] = nonce;
	}

	const response = await fetchImpl( `${ base }${ path }`, {
		...init,
		headers,
	} );

	const data = await response.json().catch( () => null );

	if ( ! response.ok ) {
		const message =
			( data && ( data.message || data.error ) ) ||
			`Request failed with status ${ response.status }`;
		const error = new Error( message );
		error.status = response.status;
		error.data = data;
		throw error;
	}

	return data;
}

/**
 * Evaluate a state against a map of typed questions.
 *
 * @param {object} params
 * @param {string|object|Array} params.state The content to evaluate.
 * @param {Record<string, object>} params.questions Map of question id => definition.
 * @param {string} [params.model] Optional model override.
 * @param {object} [options] Config overrides.
 * @returns {Promise<any>} The API response.
 */
export function systemOne( { state, questions, model } = {}, options = {} ) {
	if (
		! questions ||
		typeof questions !== 'object' ||
		Object.keys( questions ).length === 0
	) {
		return Promise.reject(
			new Error( 'At least one question is required.' )
		);
	}

	const body = { state, questions };
	if ( model ) {
		body.model = model;
	}

	return request(
		'/systemone',
		{ method: 'POST', body: JSON.stringify( body ) },
		options
	);
}

/**
 * List the models available to the configured account.
 *
 * @param {object} [options] Config overrides.
 * @returns {Promise<any>} The API response.
 */
export function listModels( options = {} ) {
	return request( '/models', { method: 'GET' }, options );
}
