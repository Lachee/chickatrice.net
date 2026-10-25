import { drizzle } from 'drizzle-orm/mysql2';
import mysql from 'mysql2/promise';
import * as chickatrice from './chickatrice.schema';
import * as cockatrice from './cockatrice';
import { config } from '#lib/server/config.ts';

/**
 * `timezone: 'Z'` makes mysql2 read and write DATETIMEs as UTC, matching
 * Servatrice's `UTC_TIMESTAMP()` columns and our own. Servatrice's uptime and
 * session times use `NOW()` instead, so the stats compare those in SQL.
 */
export const pool = mysql.createPool({
  uri: config.db.url,
  timezone: 'Z',
  connectionLimit: 10,
  // `avatar_bmp` and friends come back as Buffers; nothing here needs BigInt ids.
  supportBigNumbers: true,
});

export const db = drizzle(pool, { schema: { ...chickatrice }, mode: 'default' });

export { chickatrice, cockatrice };
