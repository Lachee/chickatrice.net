import { config } from '#lib/server/config.ts';
import { takeFlash } from '#lib/server/flash.ts';
import { staffAccess } from '#lib/staff.ts';
import type { LayoutServerLoad } from './$types';

export const load: LayoutServerLoad = async ({ locals, cookies }) => {
	const account = locals.account;
	return {
		account: account && {
			name: account.name,
			hasAvatar: account.hasAvatar,
			hasPassword: account.hasPassword,
			staff: staffAccess(account.admin).view
		},
		flash: takeFlash(cookies),
		umami: config.umami ?? null
	};
};
