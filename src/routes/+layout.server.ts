import { config } from '#lib/server/config.ts';
import { takeFlash } from '#lib/server/flash.ts';
import type { LayoutServerLoad } from './$types';

export const load: LayoutServerLoad = async ({ locals, cookies }) => {
	const account = locals.account;
	return {
		account: account && {
			name: account.name,
			hasAvatar: account.hasAvatar,
			hasPassword: account.hasPassword
		},
		flash: takeFlash(cookies),
		umami: config.umami ?? null
	};
};
