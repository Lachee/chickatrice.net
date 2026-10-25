import { requireStaff } from '#lib/server/auth/guard.ts';
import { liftBanAction, placeBan } from '#lib/server/admin/actions.ts';
import { listBans } from '#lib/server/cockatrice/moderation.ts';
import type { Actions, PageServerLoad } from './$types';

const LIMIT = 50;

export const load: PageServerLoad = async (event) => {
	requireStaff(event, 'moderate');
	const { searchParams } = event.url;
	const scope = searchParams.get('scope') === 'all' ? 'all' : 'active';
	const q = (searchParams.get('q') ?? '').trim().slice(0, 255);
	const offset = Math.max(0, Number(searchParams.get('offset')) || 0);
	return { scope, q, offset, limit: LIMIT, ...(await listBans({ scope, query: q, limit: LIMIT, offset })) };
};

export const actions: Actions = {
	ban: placeBan,
	liftBan: liftBanAction
};
