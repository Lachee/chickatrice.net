import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import mysql from 'mysql2/promise';
import { drizzle } from 'drizzle-orm/mysql2';
import { migrate } from 'drizzle-orm/mysql2/migrator';
import { readMigrationFiles } from 'drizzle-orm/migrator';
import { withLock } from './lock';

/** Relative to the working directory, matching drizzle-kit's default `out`. */
const migrationsFolder = 'drizzle';

/**
 * Drizzle's history table. Prefixed like everything else this site adds, so the
 * Servatrice database never grows a table it can't account for. Must match
 * `migrations.table` in drizzle.config.ts.
 */
export const migrationsTable = 'chickatrice_migrations';

/** Serialises migrations across replicas that start at the same time. */
const LOCK_NAME = 'chickatrice-migrations';

/**
 * Apply every migration in `drizzle/` that this database hasn't run yet.
 * Throws if a migration fails, which is meant to stop the app from starting.
 */
export async function migrateDatabase(url: string) {
  const pool = mysql.createPool({ uri: url, connectionLimit: 2, multipleStatements: false });
  try {
    const ran = await withLock(pool, LOCK_NAME, 60, async () => {
      const migrations = readMigrationFiles({ migrationsFolder });
      const before = await appliedCount(pool);

      await migrate(drizzle(pool), { migrationsFolder, migrationsTable });

      const pending = Math.max(0, migrations.length - before);
      console.log(pending ? `[db] applied ${pending} migration(s)` : '[db] schema up to date');
    });
    if (!ran)
      throw new Error('timed out waiting for another instance to finish migrating');
  } finally {
    await pool.end();
  }
}

async function appliedCount(pool: mysql.Pool): Promise<number> {
  const [ exists ] = await pool.query(
    'SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
    [ migrationsTable ],
  );
  if (!(exists as { n: number }[])[0]?.n) return 0;
  const [ rows ] = await pool.query(`SELECT COUNT(*) AS n FROM \`${migrationsTable}\``);
  return Number((rows as { n: number }[])[0]?.n ?? 0);
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  // Imported here rather than at the top so the app's own `init` hook, which
  // already holds the config, doesn't make this module depend on it.
  const { config } = await import('#lib/server/config.ts');
  await migrateDatabase(config.db.url);
}
