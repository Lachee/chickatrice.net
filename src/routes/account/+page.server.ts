import { fail, redirect } from '@sveltejs/kit';
import { config } from '#lib/server/config.ts';
import { COUNTRIES } from '#lib/countries.ts';
import { requireAccount } from '#lib/server/auth/guard.ts';
import { enabledProviders, providerIcon, providerName } from '#lib/server/auth/index.ts';
import { listIdentities, unlinkProvider } from '#lib/server/auth/identities.ts';
import { destroyOtherSessions, sessionCookieName } from '#lib/server/auth/session.ts';
import { findAccountByName, setAvatar, setCountry, setPassword } from '#lib/server/cockatrice/accounts.ts';
import { verifyPassword } from '#lib/server/cockatrice/password.ts';
import { setFlash } from '#lib/server/flash.ts';
import { AvatarError, encodeAvatar, fetchAvatar } from '#lib/server/avatar.ts';
import { eraseAccount, erasureRefusal } from '#lib/server/erase.ts';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async (event) => {
	const account = requireAccount(event);
	const identities = await listIdentities(account.id);
	const linked = new Set(identities.map((identity) => identity.provider));

	// Where Auth.js lands a sign-up (`pages.newUser`) and a link (its `callbackUrl`).
	// Anyone can type these, so each is only said when it's true.
	const { url, cookies } = event;
	if (url.searchParams.has('welcome') || url.searchParams.has('linked')) {
		const provider = url.searchParams.get('linked');
		if (url.searchParams.has('welcome') && !account.hasPassword)
			setFlash(cookies, {
				type: 'success',
				message: `Welcome! Your Cockatrice name is ${account.name}. Set a password below to log in from the client.`
			});
		else if (provider && linked.has(provider))
			setFlash(cookies, { type: 'success', message: `${providerName(provider)} is linked. Sign in with it from now on.` });
		redirect(303, '/account');
	}

	return {
		profile: {
			name: account.name,
			email: account.email,
			country: account.country,
			active: account.active,
			hasPassword: account.hasPassword,
			hasAvatar: account.hasAvatar,
			registeredAt: account.registeredAt.toISOString(),
			deleteRefusal: await erasureRefusal(account)
		},
		identities: identities.map((identity) => ({
			provider: identity.provider,
			providerName: providerName(identity.provider),
			providerIcon: providerIcon(identity.provider),
			username: identity.username,
			email: identity.email,
			hasImage: Boolean(identity.image),
			linkedAt: identity.createdAt.toISOString()
		})),
		linkable: enabledProviders.filter((provider) => !linked.has(provider.id)),
		countries: [...COUNTRIES].map(([code, name]) => ({ code, name })),
		minPasswordLength: config.cockatrice.minPasswordLength,
		maxAvatarMb: Math.floor(config.avatar.maxUploadBytes / 1024 / 1024),
		// A password-only account proves who it is with the old password; one with a
		// provider has already proved it by signing in there.
		needsCurrentPassword: account.hasPassword && identities.length === 0
	};
};

export const actions: Actions = {
	password: async (event) => {
		const account = requireAccount(event);
		const form = await event.request.formData();
		const current = String(form.get('current') ?? '');
		const password = String(form.get('password') ?? '');
		const confirm = String(form.get('confirm') ?? '');

		const min = config.cockatrice.minPasswordLength;
		if (password.length < min) return fail(400, { action: 'password', error: `Use at least ${min} characters.` });
		if (password.length > 255) return fail(400, { action: 'password', error: 'That password is too long.' });
		if (password !== confirm) return fail(400, { action: 'password', error: "The passwords don't match." });

		const identities = await listIdentities(account.id);
		if (account.hasPassword && identities.length === 0) {
			const stored = await findAccountByName(account.name);
			if (!stored || !(await verifyPassword(current, stored.passwordHash)))
				return fail(400, { action: 'password', error: 'Your current password is wrong.' });
		}

		await setPassword(account.id, password);
		// Anyone else holding a session got it with the old password (or from a shared computer).
		await destroyOtherSessions(account.id, event.cookies, event.url);
		return {
			action: 'password',
			message: account.hasPassword
				? 'Password changed. Use the new one next time you connect from Cockatrice.'
				: `Password set. You can now log in to Cockatrice as ${account.name}.`
		};
	},

	country: async (event) => {
		const account = requireAccount(event);
		const form = await event.request.formData();
		const country = String(form.get('country') ?? '').toLowerCase();
		if (!COUNTRIES.has(country)) return fail(400, { action: 'country', error: 'Pick a country from the list.' });

		await setCountry(account.id, country);
		return { action: 'country', message: 'Country updated.' };
	},

	avatar: async (event) => {
		const account = requireAccount(event);
		const form = await event.request.formData();
		const file = form.get('avatar');
		if (!(file instanceof File) || file.size === 0) return fail(400, { action: 'avatar', error: 'Choose an image to upload.' });
		if (file.size > config.avatar.maxUploadBytes)
			return fail(400, { action: 'avatar', error: `That image is too big — the limit is ${Math.floor(config.avatar.maxUploadBytes / 1024 / 1024)} MB.` });

		try {
			await setAvatar(account.id, await encodeAvatar(Buffer.from(await file.arrayBuffer())));
		} catch (e) {
			if (e instanceof AvatarError) return fail(400, { action: 'avatar', error: e.message });
			throw e;
		}
		return { action: 'avatar', message: 'Avatar updated. Cockatrice shows it after you reconnect.' };
	},

	importAvatar: async (event) => {
		const account = requireAccount(event);
		const form = await event.request.formData();
		const identity = (await listIdentities(account.id)).find((i) => i.provider === form.get('provider'));
		if (!identity?.image) return fail(400, { action: 'avatar', error: "That provider didn't share a picture." });

		try {
			await setAvatar(account.id, await fetchAvatar(identity.image));
		} catch (e) {
			if (e instanceof AvatarError) return fail(400, { action: 'avatar', error: e.message });
			throw e;
		}
		return { action: 'avatar', message: `Avatar copied from ${providerName(identity.provider)}.` };
	},

	removeAvatar: async (event) => {
		const account = requireAccount(event);
		await setAvatar(account.id, Buffer.alloc(0));
		return { action: 'avatar', message: 'Avatar removed.' };
	},

	unlink: async (event) => {
		const account = requireAccount(event);
		const form = await event.request.formData();
		const provider = String(form.get('provider') ?? '');

		const identities = await listIdentities(account.id);
		const remaining = identities.filter((identity) => identity.provider !== provider);
		if (remaining.length === identities.length) return fail(400, { action: 'providers', error: "That provider isn't linked." });

		// Without a provider the account falls back to signing in with its password,
		// so there has to be one, and signing in with it has to be allowed.
		if (remaining.length === 0 && !(account.hasPassword && config.auth.allowPasswordLogin))
			return fail(400, {
				action: 'providers',
				error: config.auth.allowPasswordLogin
					? 'Set an in-game password first, or you would have no way to sign in here.'
					: 'This is your only way to sign in, so it cannot be unlinked.'
			});

		// Checked again under a lock, in case another unlink got there first.
		if (!(await unlinkProvider(account.id, provider, remaining.length === 0)))
			return fail(400, { action: 'providers', error: 'This is your only way to sign in, so it cannot be unlinked.' });
		// Whoever signed in with it elsewhere (it may be why it's being unlinked) is signed out.
		await destroyOtherSessions(account.id, event.cookies, event.url, provider);
		return {
			action: 'providers',
			message: remaining.length
				? `${providerName(provider)} unlinked.`
				: `${providerName(provider)} unlinked. Sign in with your Cockatrice name and password from now on.`
		};
	},

	deleteAccount: async (event) => {
		const account = requireAccount(event);
		const form = await event.request.formData();

		// See erase.ts: bans the account issued would go with it.
		const refusal = await erasureRefusal(account);
		if (refusal) return fail(400, { action: 'deleteAccount', error: refusal });
		if (String(form.get('confirmName') ?? '') !== account.name)
			return fail(400, { action: 'deleteAccount', error: `Type ${account.name} exactly to confirm.` });

		// The same proof the password change asks for.
		const identities = await listIdentities(account.id);
		if (account.hasPassword && identities.length === 0) {
			const stored = await findAccountByName(account.name);
			if (!stored || !(await verifyPassword(String(form.get('current') ?? ''), stored.passwordHash)))
				return fail(400, { action: 'deleteAccount', error: 'Your password is wrong.' });
		}

		await eraseAccount(account);
		// Its sessions are gone with it; this just tidies up the browser's cookie.
		event.cookies.delete(sessionCookieName(event.url), { path: '/', secure: event.url.protocol === 'https:' });
		setFlash(event.cookies, { type: 'success', message: `${account.name} and its data have been deleted.` });
		redirect(303, '/');
	}
};
