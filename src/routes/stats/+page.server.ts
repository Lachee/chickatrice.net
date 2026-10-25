import { config } from '#lib/server/config.ts';
import {
	RANGES,
	getCountries,
	getDaily,
	getHistory,
	getLiveStatus,
	getTotals,
	isRange,
	type Range
} from '#lib/server/cockatrice/stats.ts';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ url }) => {
	const param = url.searchParams.get('range');
	const range: Range = isRange(param) ? param : '24h';

	const [status, history, daily, countries, totals] = await Promise.all([
		getLiveStatus(),
		getHistory(range),
		getDaily(),
		getCountries(),
		getTotals()
	]);

	const availability = history.length ? history.reduce((sum, p) => sum + p.availability, 0) / history.length : 0;

	return {
		range,
		ranges: Object.entries(RANGES).map(([id, { label }]) => ({ id, label })),
		rangeLabel: RANGES[range].label,
		status,
		totals,
		availability,
		users: history.map((p) => ({ t: p.t, v: p.users })),
		games: history.map((p) => ({ t: p.t, v: p.games })),
		dailyPlayers: daily.map((d) => ({ day: d.day, v: d.players })),
		dailyGames: daily.map((d) => ({ day: d.day, v: d.games })),
		countries,
		days: config.stats.days
	};
};
