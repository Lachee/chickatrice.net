import { desc, eq } from 'drizzle-orm';
import { config } from '#lib/server/config.ts';
import { chickatrice, db, pool } from '#lib/server/db/index.ts';
import { withLock } from '#lib/server/db/lock.ts';
import { listIdentities } from '#lib/server/auth/identities.ts';
import { likeEscape } from '#lib/server/admin/form.ts';
import type { Account } from './accounts';
import { renamePlayer, renameProblem } from './moderation';
import { isDuplicate, rows, table, utcEpoch } from './sql';
import { usernameProblem } from './username';

/*
 * Name changes. Servatrice has no rename, so the work is `renamePlayer`
 * (moderation.ts), which moves bans, warnings and the rest to the new name;
 * this adds the player-facing rules and the history in `chickatrice_name_changes`.
 */

const { nameChanges } = chickatrice;

export type NameHistoryEntry = {
  oldName: string | null;
  newName: string;
  source: 'signup' | 'user' | 'staff';
  /** Epoch seconds. */
  at: number;
};

const toEpoch = (date: Date) => Math.floor(date.getTime() / 1000);

/** Every name the account has had, newest first. Accounts older than the history have only the changes since. */
export async function nameHistory(accountId: number): Promise<NameHistoryEntry[]> {
  const result = await db.select().from(nameChanges)
    .where(eq(nameChanges.accountId, accountId))
    .orderBy(desc(nameChanges.id));
  return result.map((row) => ({ oldName: row.oldName, newName: row.newName, source: row.source, at: toEpoch(row.createdAt) }));
}

/** The names it had before `current`, most recent first, each once. */
export function previousNames(history: NameHistoryEntry[], current: string): string[] {
  const seen = new Set([ current.toLowerCase() ]);
  const names: string[] = [];
  for (const entry of history)
    for (const name of [ entry.oldName, entry.newName ]) {
      if (!name || seen.has(name.toLowerCase())) continue;
      seen.add(name.toLowerCase());
      names.push(name);
    }
  return names;
}

export type RecentNameChange = {
  id: number;
  accountId: number;
  oldName: string;
  newName: string;
  source: 'user' | 'staff';
  /** What the account is called now, or null if it has been deleted. */
  currentName: string | null;
  at: number;
};

/** Renames, newest first. The names accounts were made with aren't changes, so they're left out. */
export async function listRecentNameChanges({ query = '', limit, offset }: { query?: string; limit: number; offset: number }) {
  const where = query ? `AND (c.old_name LIKE ? OR c.new_name LIKE ?)` : '';
  const params = query ? [ `%${likeEscape(query)}%`, `%${likeEscape(query)}%` ] : [];
  const [ [ count ], result ] = await Promise.all([
    rows<{ total: number }>(`SELECT COUNT(*) AS total FROM chickatrice_name_changes c WHERE c.source <> 'signup' ${where}`, params),
    rows<Record<string, unknown>>(`
      SELECT c.id, c.account_id AS accountId, c.old_name AS oldName, c.new_name AS newName, c.source,
        ${utcEpoch('c.created_at')} AS at, u.name AS currentName
      FROM chickatrice_name_changes c LEFT JOIN ${table('users')} u ON u.id = c.account_id
      WHERE c.source <> 'signup' ${where}
      ORDER BY c.id DESC LIMIT ? OFFSET ?`, [ ...params, limit, offset ]),
  ]);
  return {
    total: Number(count?.total ?? 0),
    changes: result.map((row): RecentNameChange => ({
      id: Number(row.id),
      accountId: Number(row.accountId),
      oldName: String(row.oldName),
      newName: String(row.newName),
      source: row.source as 'user' | 'staff',
      currentName: (row.currentName as string | null) ?? null,
      at: Number(row.at),
    })),
  };
}

/** Seconds until the account may rename itself again, or 0 if it may now. */
async function cooldownRemaining(accountId: number): Promise<number> {
  const hours = config.cockatrice.nameChangeCooldownHours;
  if (hours <= 0) return 0;
  const [ row ] = await rows<{ remaining: number | null }>(`
    SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), MAX(created_at) + INTERVAL ? SECOND) AS remaining
    FROM chickatrice_name_changes WHERE account_id = ? AND source = 'user'`, [ Math.round(hours * 3600), accountId ]);
  return Math.max(0, Number(row?.remaining ?? 0));
}

/** `` "3 hours" ``, `` "20 minutes" ``: roughly how long to wait, rounded up. */
export function waitLength(seconds: number): string {
  const minutes = Math.ceil(seconds / 60);
  if (minutes < 60) return `${minutes} minute${minutes === 1 ? '' : 's'}`;
  const hours = Math.ceil(minutes / 60);
  return `${hours} hour${hours === 1 ? '' : 's'}`;
}

/** What the account page needs to know about renaming: whether the account may, and if not, why. */
export async function renameStatus(account: Pick<Account, 'id'>): Promise<{ refusal: string | null; wait: number }> {
  if ((await listIdentities(account.id)).length === 0)
    return { refusal: 'Link a sign-in provider below to change your name.', wait: 0 };
  const wait = await cooldownRemaining(account.id);
  return { refusal: wait ? `You can change your name again in ${waitLength(wait)}.` : null, wait };
}

export type RenameResult = { ok: true } | { ok: false; error: string };

/**
 * A player renaming themselves. Only accounts with a provider linked may (a
 * password-only account is known to its owner by its name alone), the name has
 * to pass Servatrice's rules and be free, and it can only happen once per
 * cooldown. The check and the rename happen under a lock on the account, so
 * two requests at once can't both get through.
 */
export async function renameSelf(account: Pick<Account, 'id' | 'name'>, name: string): Promise<RenameResult> {
  if (name === account.name) return { ok: false, error: 'That is already your name.' };
  const rule = usernameProblem(name, config.cockatrice.usernames);
  if (rule) return { ok: false, error: `That name ${rule}.` };

  let result: RenameResult = { ok: false, error: 'Something else is changing your name; try again in a moment.' };
  await withLock(pool, `chickatrice:rename:${account.id}`, 5, async () => {
    const { refusal } = await renameStatus(account);
    if (refusal) return void (result = { ok: false, error: refusal });
    const problem = await renameProblem(account.id, name);
    if (problem) return void (result = { ok: false, error: problem });

    try {
      await renamePlayer(account.id, account.name, name, { source: 'user' });
      result = { ok: true };
    } catch (e) {
      if (!isDuplicate(e)) throw e;
      result = { ok: false, error: `${name} was taken a moment ago.` };
    }
  });
  if (result.ok) console.log(`[account] #${account.id} renamed ${account.name} to ${name}`);
  return result;
}
