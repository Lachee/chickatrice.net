import { requireStaff } from '#lib/server/auth/guard.ts';
import { searchPlayers } from '#lib/server/cockatrice/moderation.ts';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async (event) => {
	requireStaff(event, 'moderate');
	const q = (event.url.searchParams.get('q') ?? '').trim().slice(0, 255);
	return { q, ...(await searchPlayers(q)) };
};
