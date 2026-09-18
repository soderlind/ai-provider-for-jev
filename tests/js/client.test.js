import { describe, it, expect, vi } from 'vitest';
import { systemOne, listModels } from '../../src/js/client.js';

const okResponse = ( body ) => ( {
	ok: true,
	status: 200,
	json: async () => body,
} );

const errorResponse = ( status, body ) => ( {
	ok: false,
	status,
	json: async () => body,
} );

describe( 'systemOne', () => {
	it( 'posts to the systemone endpoint with body and nonce header', async () => {
		const fetchImpl = vi.fn().mockResolvedValue(
			okResponse( {
				model: 'jev-1.13.0',
				answers: { is_urgent: { type: 'noul', noul: 0.92 } },
			} )
		);

		const result = await systemOne(
			{
				state: 'Help!',
				questions: {
					is_urgent: {
						type: 'noul',
						instructions: 'Urgent?',
					},
				},
				model: 'jev-latest',
			},
			{ fetch: fetchImpl, root: '/wp-json/ai-provider-for-jev/v1', nonce: 'abc123' }
		);

		expect( result.answers.is_urgent.noul ).toBe( 0.92 );
		expect( fetchImpl ).toHaveBeenCalledTimes( 1 );

		const [ url, init ] = fetchImpl.mock.calls[ 0 ];
		expect( url ).toBe( '/wp-json/ai-provider-for-jev/v1/systemone' );
		expect( init.method ).toBe( 'POST' );
		expect( init.headers[ 'X-WP-Nonce' ] ).toBe( 'abc123' );

		const body = JSON.parse( init.body );
		expect( body.model ).toBe( 'jev-latest' );
		expect( body.questions.is_urgent.type ).toBe( 'noul' );
	} );

	it( 'omits the model when not provided', async () => {
		const fetchImpl = vi.fn().mockResolvedValue( okResponse( {} ) );

		await systemOne(
			{ state: 's', questions: { q: { type: 'noul', instructions: 'x' } } },
			{ fetch: fetchImpl }
		);

		const body = JSON.parse( fetchImpl.mock.calls[ 0 ][ 1 ].body );
		expect( body ).not.toHaveProperty( 'model' );
	} );

	it( 'rejects when no questions are supplied', async () => {
		const fetchImpl = vi.fn();

		await expect(
			systemOne( { state: 's', questions: {} }, { fetch: fetchImpl } )
		).rejects.toThrow( /at least one question/i );
		expect( fetchImpl ).not.toHaveBeenCalled();
	} );

	it( 'throws an error carrying status and message on failure', async () => {
		const fetchImpl = vi
			.fn()
			.mockResolvedValue( errorResponse( 401, { message: 'Invalid key' } ) );

		try {
			await systemOne(
				{ state: 's', questions: { q: { type: 'noul', instructions: 'x' } } },
				{ fetch: fetchImpl }
			);
			throw new Error( 'expected rejection' );
		} catch ( error ) {
			expect( error.message ).toBe( 'Invalid key' );
			expect( error.status ).toBe( 401 );
		}
	} );
} );

describe( 'listModels', () => {
	it( 'issues a GET request to the models endpoint', async () => {
		const fetchImpl = vi
			.fn()
			.mockResolvedValue( okResponse( { models: [ { name: 'jev-latest' } ] } ) );

		const result = await listModels( { fetch: fetchImpl, root: '/base' } );

		expect( result.models[ 0 ].name ).toBe( 'jev-latest' );
		expect( fetchImpl.mock.calls[ 0 ][ 0 ] ).toBe( '/base/models' );
		expect( fetchImpl.mock.calls[ 0 ][ 1 ].method ).toBe( 'GET' );
	} );
} );
