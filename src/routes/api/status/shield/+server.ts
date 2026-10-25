import { json } from '@sveltejs/kit';
import { getLiveStatus } from '#lib/server/cockatrice/stats.ts';
import type { RequestHandler } from './$types';

const FIELDS = { users_count: 'users', games_count: 'games', uptime: 'uptime' } as const;

/**
 * A shields.io endpoint badge, as the old site served at the same address:
 * `https://img.shields.io/endpoint?url=<site>/api/status/shield`.
 * `?field=users_count|games_count|uptime`, `?label=` and `?colour=` change it.
 */
export const GET: RequestHandler = async ({ url }) => {
	const headers = { 'cache-control': 'public, max-age=15', 'access-control-allow-origin': '*' };
	const status = await getLiveStatus().catch((e) => {
		console.error('[shield] status unavailable', e);
		return null;
	});

	if (!status)
		return json({ schemaVersion: 1, label: 'status', message: 'unknown', color: 'lightgrey' }, { headers });
	if (!status.online)
		return json({ schemaVersion: 1, label: 'offline', message: status.age === null ? 'never seen' : `${status.age}s`, color: 'red' }, { headers });

	const requested = url.searchParams.get('field') ?? 'users_count';
	const field = Object.hasOwn(FIELDS, requested) ? (requested as keyof typeof FIELDS) : 'users_count';
	return json(
		{
			schemaVersion: 1,
			label: url.searchParams.get('label') ?? 'online',
			message: String(status[FIELDS[field]]),
			color: url.searchParams.get('colour') ?? url.searchParams.get('color') ?? 'blue'
		},
		{ headers }
	);
};
