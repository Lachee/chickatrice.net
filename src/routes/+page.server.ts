import { config } from '#lib/server/config.js';
import { getHistory, getLiveStatus } from '#lib/server/cockatrice/stats.js';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ depends }) => {
	depends('chickatrice:status');

	const [status, history] = await Promise.all([getLiveStatus(), getHistory('6h')]);
	return {
		status,
		recent: history.map((point) => ({ t: point.t, v: point.users })),
		server: config.server
	};
};
