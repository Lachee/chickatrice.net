import { error, redirect } from '@sveltejs/kit';
import { findAccountByName } from '#lib/server/cockatrice/accounts.ts';
import { listDecksOf, summarize } from '#lib/server/cockatrice/decks.ts';
import { isDeckAdmin } from '#lib/server/decks/access.ts';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ params, locals }) => {
	const owner = await findAccountByName(params.name);
	if (!owner) error(404, 'Nobody has that name.');
	// Names compare without case; the address should say the name as it is.
	if (owner.name !== params.name) redirect(301, `/decks/by/${encodeURIComponent(owner.name)}`);

	const own = locals.account?.id === owner.id;
	const everything = own || isDeckAdmin(locals.account);
	return {
		owner: { name: owner.name, hasAvatar: owner.hasAvatar },
		own,
		everything,
		decks: (await listDecksOf(owner.id, everything)).map(summarize)
	};
};
