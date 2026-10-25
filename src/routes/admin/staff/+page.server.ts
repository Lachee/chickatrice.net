import { requireStaff } from '#lib/server/auth/guard.ts';
import { countAdminActions } from '#lib/server/admin/log.ts';
import { getOnlinePlayers } from '#lib/server/cockatrice/metrics.ts';
import { listStaff, staffActivity } from '#lib/server/cockatrice/moderation.ts';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async (event) => {
	requireStaff(event, 'moderate');
	const [staff, online, activity, siteActions] = await Promise.all([listStaff(), getOnlinePlayers(), staffActivity(30), countAdminActions(30)]);

	const onlineNames = new Set(online.players.map((p) => p.name));
	return {
		members: staff.map((member) => ({
			...member,
			online: onlineNames.has(member.name),
			bans30d: activity.bans.get(member.id) ?? 0,
			warnings30d: activity.warnings.get(member.name.toLowerCase()) ?? 0,
			siteActions30d: siteActions.get(member.id) ?? 0
		}))
	};
};
