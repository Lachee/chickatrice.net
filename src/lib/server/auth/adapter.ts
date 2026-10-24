import type { Adapter, AdapterUser } from '@auth/sveltekit/adapters';
import { getRequestEvent } from '$app/server';
import { COUNTRIES } from '#lib/countries.js';
import { createAccount, deleteAccount, findAccountById, setActive, type Account } from '#lib/server/cockatrice/accounts.js';
import { deleteIdentity, findIdentity, insertIdentity } from './identities';
import { deleteAccountSessions, deleteSession, extendSession, findSession, insertSession } from './session';

/*
 * Auth.js' storage, over tables this site can actually use. `DrizzleAdapter`
 * (what labooru uses) wants a users table of its own shape, but a user here is
 * a row in Servatrice's `cockatrice_users`, which is never altered. So:
 *   user    -> Cockatrice account (`cockatrice_users.id`, as a string)
 *   account -> `chickatrice_identities` (provider + subject)
 *   session -> `chickatrice_sessions` (token hashed; see `session.ts`)
 * Provider tokens aren't kept: nothing here calls a provider after sign-in.
 */

const toUser = (account: Account): AdapterUser => ({
  id: String(account.id),
  name: account.name,
  email: account.email,
  emailVerified: null,
  image: null,
});

async function findUser(id: string): Promise<AdapterUser | null> {
  const account = await findAccountById(Number(id));
  return account && toUser(account);
}

/** Accounts registered by this request, so a link that loses a race can hand back the name it took. */
const createdBy = new WeakMap<object, number>();

/** Provider sessions are opened on `/auth/callback/<id>`, which says how. */
function signInMethod(): string {
  return getRequestEvent().url.pathname.match(/\/auth\/callback\/([^/]+)/)?.[1] ?? 'unknown';
}

export const adapter: Adapter = {
  async createUser(user) {
    const event = getRequestEvent();
    // Cloudflare tells us roughly where they are, which is a fine first guess at a flag.
    const country = event.request.headers.get('cf-ipcountry')?.toLowerCase() ?? null;
    const account = await createAccount({
      usernameSeed: user.name ?? user.email?.split('@')[0] ?? 'player',
      email: user.email || null,
      country: country && COUNTRIES.has(country) ? country : null,
    });
    createdBy.set(event, account.id);
    return toUser(account);
  },

  getUser: findUser,

  // Servatrice doesn't verify emails, and plenty of accounts share one. Matching
  // on them would let anyone with a provider claim an account by its email, or
  // (Auth.js' default) refuse them a new one; linking is done signed in instead.
  getUserByEmail: async () => null,

  async getUserByAccount({ provider, providerAccountId }) {
    const identity = await findIdentity(provider, providerAccountId);
    if (!identity) return null;

    const user = await findUser(String(identity.accountId));
    // Servatrice's admins deleted the account; the link is all that is left of it.
    if (!user) await deleteIdentity(identity.id);
    return user;
  },

  // Only the email provider updates users, and this site has none.
  async updateUser(user) {
    const found = await findUser(user.id);
    if (!found) throw new Error(`account ${user.id} does not exist`);
    return found;
  },

  async linkAccount(account) {
    const accountId = Number(account.userId);
    try {
      await insertIdentity(accountId, account.provider, account.providerAccountId);
    } catch (e) {
      // Two callbacks for the same new user raced and the other one won: give
      // back the name this one took — nobody has seen it yet.
      if (createdBy.get(getRequestEvent()) === accountId) await deleteAccount(accountId);
      throw e;
    }
    // A provider vouching for them is as good as the activation email they never clicked.
    await setActive(accountId);
  },

  async createSession(session) {
    const { request } = getRequestEvent();
    await insertSession(session.sessionToken, Number(session.userId), session.expires, signInMethod(), request.headers.get('user-agent'));
    return session;
  },

  async getSessionAndUser(sessionToken) {
    const row = await findSession(sessionToken);
    if (!row) return null;

    const user = await findUser(String(row.accountId));
    if (!user) {
      await deleteAccountSessions(row.accountId);
      return null;
    }
    return { session: { sessionToken, userId: user.id, expires: row.expiresAt }, user };
  },

  async updateSession(session) {
    if (session.expires) await extendSession(session.sessionToken, session.expires);
    return null;
  },

  async deleteSession(sessionToken) {
    await deleteSession(sessionToken);
  },
};
