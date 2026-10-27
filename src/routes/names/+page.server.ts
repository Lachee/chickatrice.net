import { error } from '@sveltejs/kit';
import { requireAccount } from '#lib/server/auth/guard.ts';
import { listRecentNameChanges } from '#lib/server/cockatrice/names.ts';
import { canViewNameChanges, staffAccess } from '#lib/staff.ts';
import type { PageServerLoad } from './$types';

const LIMIT = 50;

export const load: PageServerLoad = async (event) => {
	const account = requireAccount(event);
	if (!canViewNameChanges(account.admin)) error(403, "You can't see this page yet.");

	const { searchParams } = event.url;
	const q = (searchParams.get('q') ?? '').trim().slice(0, 35);
	const offset = Math.max(0, Number(searchParams.get('offset')) || 0);
	return {
		q,
		offset,
		limit: LIMIT,
		// Who changed a name is staff business; everyone else would only see the change.
		canModerate: staffAccess(account.admin).moderate,
		...(await listRecentNameChanges({ query: q, limit: LIMIT, offset }))
	};
};
