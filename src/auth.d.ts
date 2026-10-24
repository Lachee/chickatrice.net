import type { DefaultSession } from '@auth/sveltekit';

declare module '@auth/sveltekit' {
	interface Session {
		user: {
			/** `cockatrice_users.id`, as a string. See `server/auth/adapter.ts`. */
			id: string;
		} & DefaultSession['user'];
	}
}

export {};
