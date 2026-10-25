import { randomBytes } from 'node:crypto';
import { fail, redirect } from '@sveltejs/kit';
import { config } from '#lib/server/config.ts';
import { enabledProviders, providerName, signInErrorMessage } from '#lib/server/auth/index.ts';
import { createPasswordSession } from '#lib/server/auth/session.ts';
import { listIdentities } from '#lib/server/auth/identities.ts';
import { RateLimiter, addressKey } from '#lib/server/auth/ratelimit.ts';
import { safeNext } from '#lib/server/auth/guard.ts';
import { setFlash } from '#lib/server/flash.ts';
import { findAccountByName } from '#lib/server/cockatrice/accounts.ts';
import { hashPassword, verifyPassword } from '#lib/server/cockatrice/password.ts';
import type { Actions, PageServerLoad } from './$types';

// Per name, so one account can't be guessed at; per address, so many can't be.
const perName = new RateLimiter(10, 15 * 60_000);
const perAddress = new RateLimiter(40, 15 * 60_000);

/**
 * Hashed against when the name doesn't exist or has no password, so a miss takes
 * as long as a wrong password. In the format new passwords are stored in, which
 * is what most accounts end up with.
 */
const DECOY_HASH = hashPassword(randomBytes(16).toString('hex'), config.cockatrice.passwordFormat);

/** `cockatrice_users.name` is a VARCHAR(35); nothing longer can be anyone. */
const MAX_NAME_LENGTH = 35;

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
	/** Login with username + password action */
	default: async ({ request, cookies, url, getClientAddress }) => {
		if (!config.auth.allowPasswordLogin)
			return fail(403, { username: '', error: 'Signing in with a password is turned off.' });

		const form = await request.formData();
		const username = String(form.get('username') ?? '').trim();
		const password = String(form.get('password') ?? '');
		if (!username || !password)
			return fail(400, { username, error: 'Enter your Cockatrice name and password.' });

		const tooMany = (wait: number) =>
			fail(429, { username, error: `Too many attempts. Try again in ${Math.ceil(wait / 60)} minute(s).` });

		// An address that's over its limit doesn't get to count against a name.
		const addressWait = perAddress.hit(addressKey(getClientAddress()));
		if (addressWait) return tooMany(addressWait);
		if (username.length > MAX_NAME_LENGTH)
			return fail(400, { username: '', error: 'Incorrect name or password.' });

		// By account rather than by what was typed: the name's collation matches
		// `bob`, `Bób` and `ｂｏｂ` alike, and each would get its own attempts.
		const account = await findAccountByName(username);
		const nameKey = account ? `#${account.id}` : username.toLowerCase();
		const nameWait = perName.hit(nameKey);
		if (nameWait) return tooMany(nameWait);

		const stored = account?.passwordHash || (await DECOY_HASH);
		const correct = (await verifyPassword(password, stored)) && Boolean(account?.passwordHash);
		if (!account || !correct)
			return fail(400, { username, error: 'Incorrect name or password.' });

		// Only said once the password is right, so it doesn't tell strangers which accounts are linked.
		const linked = await listIdentities(account.id);
		if (linked.length)
			return fail(400, {
				username,
				error: `This account signs in with ${linked.map((identity) => providerName(identity.provider)).join(' or ')}. Use that button instead.`
			});

		perName.reset(nameKey);
		await createPasswordSession(cookies, url, account.id, request.headers.get('user-agent'));
		redirect(303, safeNext(url.searchParams.get('next')));
	}
};
