import { requireStaff } from '#lib/server/auth/guard.ts';
import { getOnlinePlayers } from '#lib/server/cockatrice/metrics.ts';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async (event) => {
	const staff = requireStaff(event, 'view');
	event.depends('chickatrice:online');
	const online = await getOnlinePlayers();

	// Developers get Servatrice's stats, not its moderation data: no addresses or client ids.
	const players = online.players.map((player) =>
		staff.access.moderate ? player : { ...player, ip: '', clientId: '' }
	);
	return { ...online, players };
};
