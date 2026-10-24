import { json } from '@sveltejs/kit';
import { getLiveStatus } from '#lib/server/cockatrice/stats.js';
import type { RequestHandler } from './$types';

/** The server's current status, for bots and other sites. */
export const GET: RequestHandler = async () => {
	const status = await getLiveStatus();
	return json(status, {
		headers: { 'cache-control': 'public, max-age=15', 'access-control-allow-origin': '*' }
	});
};
