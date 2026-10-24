import { SvelteKitAuth } from '@auth/sveltekit';
import type { Provider } from '@auth/sveltekit/providers';
import { getRequestEvent } from '$app/server';
import { config, type AuthProviderConfig } from '#lib/server/config.js';
import { findAccountById } from '#lib/server/cockatrice/accounts.js';
import { adapter } from './adapter';
import { findIdentity, hasProvider, refreshIdentity, type ProfileDetails } from './identities';
import { SESSION_MAX_AGE, sessionAccountId } from './session';

/*
 * Provider sign-in is Auth.js, set up as in labooru, with database sessions on
 * this site's own tables (see `adapter.ts`). Its routes live under `/auth`:
 * the login and account pages post straight to `/auth/signin/<id>`, providers
 * call back to `/auth/callback/<id>`, and the menu posts to `/auth/signout`.
 *
 * Not through `@auth/sveltekit`'s `signIn` form action, as labooru does: it
 * hands the provider's address to SvelteKit's `redirect()`, which SvelteKit 3
 * refuses for other origins. A form post to the endpoint gets the same redirect
 * from Auth.js itself, and SvelteKit's CSRF check still turns away cross-site
 * posts (`@auth/sveltekit` switches off Auth.js' own token for that reason).
 */

/** Reads the configured claims off a provider's profile. */
function readProfile(provider: AuthProviderConfig, profile: Record<string, unknown>): { subject: string | null } & ProfileDetails {
  const read = (names: string[]) => {
    for (const name of names) {
      const value = profile[name];
      if (typeof value === 'string' && value !== '') return value;
      if (typeof value === 'number') return String(value);
    }
    return null;
  };

  return {
    subject: read(provider.claims.id),
    username: read(provider.claims.username),
    email: read(provider.claims.email),
    image: read(provider.claims.image),
  };
}

/** Translates a configured OAuth2/OIDC provider into the shape Auth.js expects. */
function createProvider(provider: AuthProviderConfig): Provider {
  const { endpoints } = provider;
  const oidc = provider.type === 'oidc';
  const scope = provider.scope ?? (oidc ? 'openid email profile' : undefined);

  return {
    id: provider.id,
    name: provider.name ?? provider.id,
    type: provider.type,
    clientId: provider.clientId,
    clientSecret: provider.clientSecret,
    ...(provider.issuer ? { issuer: provider.issuer } : {}),
    ...(endpoints.token ? { token: endpoints.token } : {}),
    ...(endpoints.userinfo ? { userinfo: endpoints.userinfo } : {}),
    authorization: {
      ...(endpoints.authorization ? { url: endpoints.authorization } : {}),
      params: scope ? { scope } : {},
    },
    // A nonce only means something when there's an ID token to carry it.
    checks: oidc ? [ 'pkce', 'state', 'nonce' ] : [ 'pkce', 'state' ],
    profile(profile: Record<string, unknown>) {
      const { subject, username, email, image } = readProfile(provider, profile);
      if (!subject)
        throw new Error(`provider ${provider.id} returned no ${provider.claims.id.join('/')} claim to identify the user by`);

      // `name` seeds the in-game name of a new account; see `adapter.createUser`.
      return { id: subject, name: username, email, image };
    },
  } as Provider;
}

/** What the login and account pages need to render their sign-in buttons. */
export const enabledProviders = config.auth.providers.map((provider) => ({
  id: provider.id,
  name: provider.name ?? provider.id,
}));

export const providerName = (id: string) => enabledProviders.find((provider) => provider.id === id)?.name ?? id;

/**
 * The `?error=` codes Auth.js sends to the login page, and the ones `signIn`
 * below sends. Only a code travels in the URL, never the message, so nobody can
 * craft a link that makes the site say something it didn't.
 */
const SIGN_IN_ERRORS: Record<string, string> = {
  OAuthAccountNotLinked: 'That sign-in is already linked to a different Cockatrice account.',
  ProviderAlreadyLinked: 'This account already has a sign-in from that provider linked. Unlink it first.',
  RegistrationClosed: 'New accounts are not being accepted. Sign in with your Cockatrice name and password, then link this provider from your account page.',
  OAuthCallbackError: "The provider didn't finish signing you in. Please try again.",
};

export const signInErrorMessage = (code: string) => SIGN_IN_ERRORS[code] ?? "Signing in didn't work. Please try again.";

export const { handle } = SvelteKitAuth({
  secret: config.auth.secret,
  trustHost: true,
  adapter,
  providers: config.auth.providers.map(createProvider),
  session: {
    strategy: 'database',
    maxAge: SESSION_MAX_AGE,
    // Pushed out again once past the halfway point, so someone who visits keeps their sign-in.
    updateAge: SESSION_MAX_AGE / 2,
  },
  pages: {
    signIn: '/login',
    error: '/login',
    // Only ever reached by an account created moments ago, which has no password yet.
    newUser: '/account?welcome',
  },
  callbacks: {
    /**
     * Auth.js links a provider to whoever is signed in, and otherwise signs in
     * (or registers) whoever the provider says it is. This adds this site's
     * rules on top: one link per provider per account, and no new accounts
     * when registration is off.
     */
    async signIn({ account }) {
      if (!account) return false;

      const { cookies, url } = getRequestEvent();
      const current = await sessionAccountId(cookies, url);
      const identity = await findIdentity(account.provider, account.providerAccountId);
      const owner = identity && await findAccountById(identity.accountId);

      if (current !== null) {
        // Linking. A sign-in that belongs to someone else, Auth.js turns away itself.
        if (!owner && await hasProvider(current, account.provider))
          return '/login?error=ProviderAlreadyLinked';
        return true;
      }

      if (!owner && !config.auth.allowRegistration)
        return '/login?error=RegistrationClosed';
      return true;
    },

    // Auth.js' default session callback narrows `user` down to name/email/image, so the id has to be put back on.
    session: ({ session, user }) => ({ ...session, user: { ...session.user, id: user.id } }),
  },
  events: {
    async signIn({ account, profile }) {
      const provider = config.auth.providers.find((candidate) => candidate.id === account?.provider);
      if (!account || !profile || !provider) return;
      await refreshIdentity(account.provider, account.providerAccountId, readProfile(provider, profile));
    },
  },
});
