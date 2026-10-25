// See https://svelte.dev/docs/kit/types#app.d.ts
// for information about these interfaces.
// `@auth/sveltekit` augments App.Locals with `auth()`/`getSession()`.
declare global {
	namespace App {
		// interface Error {}
		interface Locals {
			/** The Cockatrice account signed in, resolved in `hooks.server.ts`. Null when signed out. */
			account: import('#lib/server/cockatrice/accounts.ts').Account | null;
		}
		// interface PageData {}
		// interface PageState {}
		// interface Platform {}
	}
}

export {};
