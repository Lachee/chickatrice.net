import type { ResultSetHeader } from 'mysql2';
import { config } from '#lib/server/config.ts';
import { pool } from '#lib/server/db/index.ts';

/*
 * Raw SQL against Servatrice's tables, for the admin pages. Most of these
 * tables aren't declared in db/cockatrice.ts (and some exist only in newer
 * schemas; see capabilities.ts), so they're queried by hand.
 *
 * Servatrice stamps its rows with `NOW()`, the database's local time, so those
 * leave as `UNIX_TIMESTAMP(col)`. Account dates are UTC (`UTC_TIMESTAMP()`),
 * so they go through `utcEpoch` instead. Either way, times reach the app as
 * epoch seconds, never as DATETIMEs a time zone could shift.
 */

/** A Servatrice table's quoted name, prefix included: `table('bans')` → `` `cockatrice_bans` ``. */
export const table = (name: string) => `\`${config.cockatrice.prefix}_${name}\``;

/** Epoch seconds of a DATETIME column holding UTC. Zero dates come out NULL. */
export const utcEpoch = (column: string) => `IF(${column} > '1970-01-01', TIMESTAMPDIFF(SECOND, '1970-01-01', ${column}), NULL)`;

export async function rows<T>(query: string, params: unknown[] = []): Promise<T[]> {
  const [ result ] = await pool.query(query, params);
  return result as T[];
}

export async function execute(query: string, params: unknown[] = []): Promise<ResultSetHeader> {
  const [ result ] = await pool.query(query, params);
  return result as ResultSetHeader;
}

/** A number column that may be NULL. */
export const numberOrNull = (value: unknown) => (value === null || value === undefined ? null : Number(value));

export const ER_DUP_ENTRY = 'ER_DUP_ENTRY';
export const isDuplicate = (e: unknown) => (e as { code?: string }).code === ER_DUP_ENTRY;
