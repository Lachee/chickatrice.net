import { config } from '#lib/server/config.ts';
import { UNKNOWN_STATUS, getHistory, getLiveStatus } from '#lib/server/cockatrice/stats.ts';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ depends }) => {
	depends('chickatrice:status');

	// The front page still renders with the database down, showing the server as offline.
	const [status, history] = await Promise.all([getLiveStatus(), getHistory('6h')]).catch((e) => {
		console.error('[status] unavailable', e);
		return [UNKNOWN_STATUS, []] as const;
	});
	return {
		status,
		recent: history.map((point) => ({ t: point.t, v: point.users })),
		server: config.server
	};
};
