import { fail, redirect } from '@sveltejs/kit';
import { config } from '#lib/server/config.js';
import { enabledProviders, providerName, signInErrorMessage } from '#lib/server/auth/index.js';
import { createPasswordSession } from '#lib/server/auth/session.js';
import { listIdentities } from '#lib/server/auth/identities.js';
import { RateLimiter } from '#lib/server/auth/ratelimit.js';
import { safeNext } from '#lib/server/auth/guard.js';
import { setFlash } from '#lib/server/flash.js';
import { findAccountByName } from '#lib/server/cockatrice/accounts.js';
import { verifyPassword } from '#lib/server/cockatrice/password.js';
import type { Actions, PageServerLoad } from './$types';

// Per name, so one account can't be guessed at; per address, so many can't be.
const perName = new RateLimiter(10, 15 * 60_000);
const perAddress = new RateLimiter(40, 15 * 60_000);

/** Hashed against when the name doesn't exist, so a miss takes as long as a wrong password. */
const DECOY_HASH = 'AAAAAAAAAAAAAAAA' + 'A'.repeat(88);

export const load: PageServerLoad = async ({ locals, url, cookies }) => {
	const next = safeNext(url.searchParams.get('next'));
	// Auth.js sends every failed sign-in here, linking from the account page included.
	const code = url.searchParams.get('error');
	const error = code ? signInErrorMessage(code) : null;

	if (locals.account) {
		if (error) {
			setFlash(cookies, { type: 'error', message: error });
			redirect(303, '/account');
		}
		redirect(303, next);
	}

	return {
		next,
		error,
		providers: enabledProviders,
		allowPasswordLogin: config.auth.allowPasswordLogin,
		allowRegistration: config.auth.allowRegistration
	};
};

export const actions: Actions = {
	default: async ({ request, cookies, url, getClientAddress }) => {
		if (!config.auth.allowPasswordLogin)
			return fail(403, { username: '', error: 'Signing in with a password is turned off.' });

		const form = await request.formData();
		const username = String(form.get('username') ?? '').trim();
		const password = String(form.get('password') ?? '');
		if (!username || !password)
			return fail(400, { username, error: 'Enter your Cockatrice name and password.' });

		const wait = Math.max(perAddress.hit(getClientAddress()), perName.hit(username.toLowerCase()));
		if (wait)
			return fail(429, { username, error: `Too many attempts. Try again in ${Math.ceil(wait / 60)} minute(s).` });

		const account = await findAccountByName(username);
		const correct = await verifyPassword(password, account?.passwordHash ?? DECOY_HASH);
		if (!account || !correct)
			return fail(400, { username, error: 'Incorrect name or password.' });

		// Only said once the password is right, so it doesn't tell strangers which accounts are linked.
		const linked = await listIdentities(account.id);
		if (linked.length)
			return fail(400, {
				username,
				error: `This account signs in with ${linked.map((identity) => providerName(identity.provider)).join(' or ')}. Use that button instead.`
			});

		perName.reset(username.toLowerCase());
		await createPasswordSession(cookies, url, account.id, request.headers.get('user-agent'));
		redirect(303, safeNext(url.searchParams.get('next')));
	}
};
