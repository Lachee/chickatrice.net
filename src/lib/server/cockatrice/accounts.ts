import { eq, inArray, sql } from 'drizzle-orm';
import { config } from '#lib/server/config.js';
import { db, cockatrice } from '#lib/server/db/index.js';
import { capabilities } from './capabilities';
import { hashPassword } from './password';
import { usernameCandidates } from './username';

const { users } = cockatrice;

/** A Cockatrice account as the site shows it. Never carries the password hash or avatar bytes. */
export type Account = {
  id: number;
  name: string;
  email: string;
  country: string;
  /** Servatrice staff bitfield: 1 admin, 2 moderator, 4 judge, 8 developer. */
  admin: number;
  active: boolean;
  hasPassword: boolean;
  hasAvatar: boolean;
  registeredAt: Date;
};

const accountColumns = {
  id: users.id,
  name: users.name,
  email: users.email,
  country: users.country,
  admin: users.admin,
  active: users.active,
  registeredAt: users.registrationDate,
  hasPassword: sql<number>`${users.passwordSha512} <> ''`,
  hasAvatar: sql<number>`LENGTH(${users.avatarBmp}) > 0`,
};

type AccountRow = { [K in keyof typeof accountColumns]: unknown };

function toAccount(row: AccountRow): Account {
  return {
    id: Number(row.id),
    name: String(row.name),
    email: String(row.email),
    country: String(row.country ?? '').toLowerCase(),
    admin: Number(row.admin),
    active: Number(row.active) === 1,
    hasPassword: Number(row.hasPassword) === 1,
    hasAvatar: Number(row.hasAvatar) === 1,
    registeredAt: row.registeredAt as Date,
  };
}

export async function findAccountById(id: number): Promise<Account | null> {
  const [ row ] = await db.select(accountColumns).from(users).where(eq(users.id, id)).limit(1);
  return row ? toAccount(row) : null;
}

/** Names compare case-insensitively, as Servatrice's `utf8mb4_unicode_ci` column does. */
export async function findAccountByName(name: string): Promise<(Account & { passwordHash: string }) | null> {
  const [ row ] = await db
    .select({ ...accountColumns, passwordHash: users.passwordSha512 })
    .from(users)
    .where(eq(users.name, name))
    .limit(1);
  return row ? { ...toAccount(row), passwordHash: row.passwordHash } : null;
}

export type NewAccount = {
  /** What the in-game name is derived from; see `usernameCandidates`. */
  usernameSeed: string;
  email: string | null;
  country: string | null;
};

const ER_DUP_ENTRY = 'ER_DUP_ENTRY';

/**
 * Registers a Cockatrice account the way Servatrice's own `registerUser` does,
 * but with no password yet — the owner sets one from their account page. The
 * name is the seed bent to fit the server's rules, with a number on the end if
 * it is taken.
 */
export async function createAccount({ usernameSeed, email, country }: NewAccount): Promise<Account> {
  const candidates = usernameCandidates(usernameSeed, config.cockatrice.usernames);

  // Check names in batches rather than one round trip each, then insert; a
  // racing signup that takes the same name just moves us on to the next one.
  while (true) {
    // `.next()` by hand: breaking out of a for-of would close the generator.
    const batch: string[] = [];
    while (batch.length < 25) {
      const next = candidates.next();
      if (next.done) break;
      batch.push(next.value);
    }
    if (batch.length === 0)
      throw new Error(`no free username could be made from "${usernameSeed}"`);

    const taken = await db.select({ name: users.name }).from(users).where(inArray(users.name, batch));
    const takenNames = new Set(taken.map((row) => row.name.toLowerCase()));

    for (const name of batch) {
      if (takenNames.has(name.toLowerCase())) continue;
      try {
        const [ result ] = await db.insert(users).values({
          name,
          admin: 0,
          realname: '',
          passwordSha512: '',
          email: (email ?? '').toLowerCase().slice(0, 255),
          country: country ?? '',
          avatarBmp: Buffer.alloc(0),
          registrationDate: sql`UTC_TIMESTAMP()`,
          // The provider vouched for them, which is what activation by email is for.
          active: 1,
          clientid: '',
          adminnotes: '',
          privlevel: 'NONE',
          privlevelStartDate: sql`UTC_TIMESTAMP()`,
          privlevelEndDate: sql`UTC_TIMESTAMP()`,
        });
        const account = await findAccountById(result.insertId);
        if (!account) throw new Error(`account ${name} vanished after being created`);
        return account;
      } catch (e) {
        if ((e as { code?: string }).code === ER_DUP_ENTRY) continue;
        throw e;
      }
    }
  }
}

/**
 * Sets the in-game password, the way Servatrice's `changeUserPassword` does:
 * the change date is stamped, and any legacy backup of the old password is
 * dropped so old clients can't keep using it.
 */
export async function setPassword(accountId: number, password: string): Promise<void> {
  const [ hash, caps ] = await Promise.all([
    hashPassword(password, config.cockatrice.passwordFormat),
    capabilities(),
  ]);

  const assignments = [ sql`password_sha512 = ${hash}` ];
  if (caps.passwordChangedDate) assignments.push(sql`passwordLastChangedDate = NOW()`);
  if (caps.forcePasswordChange) assignments.push(sql`force_password_change = 0`);
  if (caps.legacyBackup) assignments.push(sql`password_legacy_backup = ''`, sql`password_legacy_backup_used_at = NULL`);

  await db.execute(sql`UPDATE ${users} SET ${sql.join(assignments, sql`, `)} WHERE id = ${accountId}`);
}

export async function setCountry(accountId: number, country: string): Promise<void> {
  await db.update(users).set({ country: country.toLowerCase() }).where(eq(users.id, accountId));
}

/** Store an already-encoded image, or an empty buffer to clear it. */
export async function setAvatar(accountId: number, image: Buffer): Promise<void> {
  await db.update(users).set({ avatarBmp: image }).where(eq(users.id, accountId));
}

export async function setActive(accountId: number): Promise<void> {
  await db.update(users).set({ active: 1 }).where(eq(users.id, accountId));
}

/** Only for undoing an account this site created moments ago; see `accountForProfile`. */
export async function deleteAccount(accountId: number): Promise<void> {
  await db.delete(users).where(eq(users.id, accountId));
}

export async function getAvatar(name: string): Promise<Buffer | null> {
  const [ row ] = await db.select({ avatar: users.avatarBmp }).from(users).where(eq(users.name, name)).limit(1);
  return row?.avatar?.length ? row.avatar : null;
}
