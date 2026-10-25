import { sql } from 'drizzle-orm';
import { config } from '#lib/server/config.ts';
import { db, pool } from '#lib/server/db/index.ts';
import type { Account } from '#lib/server/cockatrice/accounts.ts';

/*
 * "Delete my account": the Cockatrice account and everything personal hanging
 * off it, in one transaction. Servatrice has no way to delete a user (operators
 * do it by hand), so this follows its schema (docker/servatrice.sql).
 *
 * Many of these rows would go anyway through Servatrice's ON DELETE CASCADE
 * keys, but not every database has every key, so each is deleted outright.
 * Others are only tied to the user by name, or only SET NULL (shared decks
 * would stay up), so they have to be.
 *
 * Kept on purpose: bans, warnings and reports. They're the server's moderation
 * record, and bans go by name, IP and client id, so deleting them would let
 * anyone shed a ban by deleting their account. Games stay too: they're other
 * players' history as well, and only carry names.
 *
 * Accounts that ever issued a ban are refused (see `erasureRefusal`):
 * `bans.id_admin` cascades, so deleting one would delete every ban it issued.
 */

type Key = 'id' | 'name';

/** Servatrice's tables, without the prefix: [table, column, which of the account's keys it holds]. */
const SERVATRICE: [string, string, Key][] = [
  ['deck_share', 'created_by', 'id'], // items cascade from it
  ['decklist_files', 'id_user', 'id'],
  ['decklist_folders', 'id_user', 'id'],
  ['buddylist', 'id_user1', 'id'],
  ['buddylist', 'id_user2', 'id'],
  ['ignorelist', 'id_user1', 'id'],
  ['ignorelist', 'id_user2', 'id'],
  ['replays_access', 'id_player', 'id'],
  ['log', 'sender_id', 'id'], // chat they sent
  ['user_analytics', 'id', 'id'],
  ['activation_emails', 'name', 'name'],
  ['forgot_password', 'name', 'name'],
  ['sessions', 'user_name', 'name'], // connection history, with IP addresses
  ['audit', 'name', 'name'], // login attempts, likewise
  ['users', 'id', 'id'] // last: the rest hang off it
];

/** Ours, including what 0001_legacy_import.sql kept of the old site's. */
const CHICKATRICE: [string, string, Key][] = [
  ['chickatrice_identities', 'account_id', 'id'],
  ['chickatrice_sessions', 'account_id', 'id'],
  ['legacy_chickatrice_users', 'cockatrice_id', 'id'], // email, Discord id, API keys
  ['legacy_chickatrice_users_backup', 'id', 'id'] // original email
];

/**
 * Why the account can't be deleted from the site, or null if it can. Checking
 * `admin` alone isn't enough: a moderator who has since been demoted still
 * has their bans hanging off their id.
 */
export async function erasureRefusal(account: Pick<Account, 'id' | 'admin'>): Promise<string | null> {
  if (account.admin)
    return "Staff accounts can't be deleted here. Ask the server's admin.";

  const [rows] = await pool.query(
    `SELECT 1 FROM \`${config.cockatrice.prefix}_bans\` WHERE id_admin = ? LIMIT 1`,
    [account.id]
  );
  if ((rows as unknown[]).length)
    return "This account has issued bans, which would be deleted with it. Ask the server's admin.";

  return null;
}

/** Deletes the account and its data. Returns how many rows went, for the log. */
export async function eraseAccount(account: Pick<Account, 'id' | 'name' | 'admin'>): Promise<number> {
  const refusal = await erasureRefusal(account);
  if (refusal) throw new Error(`refusing to erase ${account.name}: ${refusal}`);

  const targets = [
    ...SERVATRICE.map(([table, column, key]): [string, string, Key] => [`${config.cockatrice.prefix}_${table}`, column, key]),
    ...CHICKATRICE
  ];

  // Older schemas lack some tables (deck_share, for one), and legacy_* only
  // exist where the old site's database was imported. Asking after the column,
  // not just the table, means a schema that differs is skipped rather than fatal.
  const [rows] = await pool.query(
    `SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME) AS name FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?)`,
    [[...new Set(targets.map(([table]) => table))]]
  );
  const present = new Set((rows as { name: string }[]).map((row) => row.name));
  const users = `${config.cockatrice.prefix}_users.id`;
  if (!present.has(users)) throw new Error(`${users} is missing, so the account can't be deleted`);

  let deleted = 0;
  await db.transaction(async (tx) => {
    for (const [table, column, key] of targets) {
      if (!present.has(`${table}.${column}`)) continue;
      const [result] = await tx.execute(
        sql`DELETE FROM ${sql.identifier(table)} WHERE ${sql.identifier(column)} = ${account[key]}`
      );
      deleted += result.affectedRows;
    }
  });

  console.log(`[account] erased #${account.id} ${account.name} (${deleted} rows)`);
  return deleted;
}
