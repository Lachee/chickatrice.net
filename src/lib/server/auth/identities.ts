import { and, asc, eq } from 'drizzle-orm';
import { db, chickatrice } from '#lib/server/db/index.ts';

/*
 * Provider sign-ins linked to Cockatrice accounts: Auth.js' "accounts", kept in
 * `chickatrice_identities`. The adapter creates and finds them; Auth.js decides
 * when (see `auth/index.ts`).
 */

const { identities } = chickatrice;

export type Identity = typeof identities.$inferSelect;

/** What a provider said about the person who signed in, for display only. */
export type ProfileDetails = {
  username: string | null;
  email: string | null;
  image: string | null;
};

export function listIdentities(accountId: number): Promise<Identity[]> {
  return db.select().from(identities).where(eq(identities.accountId, accountId)).orderBy(asc(identities.createdAt));
}

export async function findIdentity(provider: string, subject: string): Promise<Identity | null> {
  const [ row ] = await db.select().from(identities)
    .where(and(eq(identities.provider, provider), eq(identities.subject, subject)))
    .limit(1);
  return row ?? null;
}

export async function hasProvider(accountId: number, provider: string): Promise<boolean> {
  const [ row ] = await db.select({ id: identities.id }).from(identities)
    .where(and(eq(identities.accountId, accountId), eq(identities.provider, provider)))
    .limit(1);
  return Boolean(row);
}

/** The display details are filled in by `refreshIdentity` once the sign-in finishes. */
export async function insertIdentity(accountId: number, provider: string, subject: string) {
  const now = new Date();
  await db.insert(identities).values({ accountId, provider, subject, createdAt: now, lastLoginAt: now });
}

/** Keeps the provider's display details current; they're only ever shown, never trusted. */
export async function refreshIdentity(provider: string, subject: string, details: ProfileDetails) {
  await db.update(identities).set({
    username: details.username?.slice(0, 255) ?? null,
    email: details.email?.slice(0, 255) ?? null,
    image: details.image?.slice(0, 2048) ?? null,
    lastLoginAt: new Date(),
  }).where(and(eq(identities.provider, provider), eq(identities.subject, subject)));
}

export async function deleteIdentity(id: number) {
  await db.delete(identities).where(eq(identities.id, id));
}

/**
 * Removes the account's link to `provider`. Unless `mayBeLast`, refuses (returns
 * false) when it is the account's only link. The links are locked while that's
 * decided, so two unlinks at once can't each leave the other one's behind.
 */
export async function unlinkProvider(accountId: number, provider: string, mayBeLast: boolean): Promise<boolean> {
  return db.transaction(async (tx) => {
    const linked = await tx.select({ provider: identities.provider }).from(identities)
      .where(eq(identities.accountId, accountId))
      .for('update');
    if (!linked.some((row) => row.provider === provider)) return false;
    if (linked.length === 1 && !mayBeLast) return false;

    await tx.delete(identities).where(and(eq(identities.accountId, accountId), eq(identities.provider, provider)));
    return true;
  });
}
