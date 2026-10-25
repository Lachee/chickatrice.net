import { createHash, randomBytes } from 'node:crypto';
import type { Cookies } from '@sveltejs/kit';
import { and, eq, gt, lte, ne, sql } from 'drizzle-orm';
import { config } from '#lib/server/config.ts';
import { db, chickatrice } from '#lib/server/db/index.ts';

/*
 * Sign-ins are Auth.js database sessions: an opaque random token in a cookie,
 * kept server-side as a SHA-256 hash (`chickatrice_sessions`), so a leaked
 * database can't be replayed as a cookie and signing someone out everywhere is
 * one DELETE. Auth.js only ever hands the adapter the raw token, which is
 * hashed here on the way in.
 *
 * Password sign-ins can't go through Auth.js (its credentials provider only
 * works with JWT sessions), so `createPasswordSession` writes the same row and
 * the same cookie Auth.js would, and from then on Auth.js treats it as its own.
 */

const { sessions } = chickatrice;

export const SESSION_MAX_AGE = config.auth.sessionDays * 86_400;

export type SessionRow = typeof sessions.$inferSelect;

const hashToken = (token: string) => createHash('sha256').update(token).digest('hex');

/**
 * The cookie Auth.js keeps the token in. It decides `__Secure-` by the request's
 * protocol, which `server.js` keeps in step with SvelteKit's `url`.
 */
export const sessionCookieName = (url: URL) => `${url.protocol === 'https:' ? '__Secure-' : ''}authjs.session-token`;

/** `method` records how: `password` or a provider id. */
export async function insertSession(token: string, accountId: number, expiresAt: Date, method: string, userAgent: string | null) {
  await db.insert(sessions).values({
    id: hashToken(token),
    accountId,
    method,
    createdAt: new Date(),
    expiresAt,
    userAgent: userAgent?.slice(0, 255) ?? null,
  });
}

/** The live session behind a token, or null if there's none or it has expired. */
export async function findSession(token: string): Promise<SessionRow | null> {
  const [ row ] = await db
    .select()
    .from(sessions)
    .where(and(eq(sessions.id, hashToken(token)), gt(sessions.expiresAt, sql`UTC_TIMESTAMP()`)))
    .limit(1);
  return row ?? null;
}

export async function extendSession(token: string, expiresAt: Date) {
  await db.update(sessions).set({ expiresAt }).where(eq(sessions.id, hashToken(token)));
}

export async function deleteSession(token: string) {
  await db.delete(sessions).where(eq(sessions.id, hashToken(token)));
}

export async function deleteAccountSessions(accountId: number) {
  await db.delete(sessions).where(eq(sessions.accountId, accountId));
}

/** Signs the browser in as `accountId` after it gave the right in-game password. */
export async function createPasswordSession(cookies: Cookies, url: URL, accountId: number, userAgent: string | null) {
  const token = randomBytes(32).toString('base64url');
  const expiresAt = new Date(Date.now() + SESSION_MAX_AGE * 1000);
  await insertSession(token, accountId, expiresAt, 'password', userAgent);

  cookies.set(sessionCookieName(url), token, {
    path: '/',
    httpOnly: true,
    sameSite: 'lax',
    secure: url.protocol === 'https:',
    expires: expiresAt,
  });
}

/** The account the browser is signed in as, going by its cookie alone. */
export async function sessionAccountId(cookies: Cookies, url: URL): Promise<number | null> {
  const token = cookies.get(sessionCookieName(url));
  return token ? (await findSession(token))?.accountId ?? null : null;
}

/** Signs the account out of every browser except this one; only those signed in by `method`, if given. */
export async function destroyOtherSessions(accountId: number, cookies: Cookies, url: URL, method?: string) {
  const token = cookies.get(sessionCookieName(url));
  await db.delete(sessions).where(and(
    eq(sessions.accountId, accountId),
    method ? eq(sessions.method, method) : undefined,
    token ? ne(sessions.id, hashToken(token)) : undefined,
  ));
}

/** Deletes sessions that have expired. Returns how many. */
export async function pruneSessions(): Promise<number> {
  const [ result ] = await db.delete(sessions).where(lte(sessions.expiresAt, sql`UTC_TIMESTAMP()`));
  return result.affectedRows;
}
