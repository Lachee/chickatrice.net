import { requireStaff } from '#lib/server/auth/guard.ts';
import { listAdminLog } from '#lib/server/admin/log.ts';
import { RANGES, getHistory, getLiveStatus, getTotals, isRange, type Range } from '#lib/server/cockatrice/stats.ts';
import {
	getClientVersions,
	getConnectionTypes,
	getModerationCounts,
	getOnlinePlayers,
	getRegistrations,
	getTraffic
} from '#lib/server/cockatrice/metrics.ts';
import { listBans } from '#lib/server/cockatrice/moderation.ts';
import { listServerMessages } from '#lib/server/cockatrice/settings.ts';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async (event) => {
	const staff = requireStaff(event, 'view');
	const param = event.url.searchParams.get('range');
	const range: Range = isRange(param) ? param : '24h';

	const [status, totals, counts, online, history, traffic, registrations, versions, connections, messages, recentBans, recentActions] =
		await Promise.all([
			getLiveStatus(),
			getTotals(),
			getModerationCounts(),
			getOnlinePlayers(),
			getHistory(range),
			getTraffic(range),
			getRegistrations(),
			getClientVersions(),
			getConnectionTypes(),
			listServerMessages(1),
			staff.access.moderate ? listBans({ scope: 'all', limit: 5 }).then((result) => result.bans) : Promise.resolve([]),
			staff.access.moderate ? listAdminLog({ limit: 8 }) : Promise.resolve([])
		]);

	return {
		range,
		ranges: Object.keys(RANGES),
		rangeLabel: RANGES[range].label,
		status,
		totals,
		counts,
		moderators: online.moderators,
		users: history.map((p) => ({ t: p.t, v: p.users })),
		games: history.map((p) => ({ t: p.t, v: p.games })),
		received: traffic.map((p) => ({ t: p.t, v: p.rx })),
		registrations,
		versions,
		connections,
		serverMessage: messages[0] ?? null,
		recentBans,
		recentActions
	};
};
