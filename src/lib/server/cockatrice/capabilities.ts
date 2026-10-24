import { config } from '#lib/server/config.js';
import { pool } from '#lib/server/db/index.js';

/**
 * Which optional `cockatrice_users` columns this database has. Servatrice adds
 * columns as its schema version climbs, and this site has to work against
 * whichever version the server runs — so rather than declaring them, it asks.
 */
export type Capabilities = {
  /** `passwordLastChangedDate` — stamped on every password change. */
  passwordChangedDate: boolean;
  /** `force_password_change` — cleared once the user has picked a new password. */
  forcePasswordChange: boolean;
  /**
   * `password_legacy_backup` (schema 38+): an encrypted copy of the old legacy
   * hash. Servatrice clears it on a password change, and so must we, or old
   * clients could keep logging in with the previous password.
   */
  legacyBackup: boolean;
};

let cached: Promise<Capabilities> | undefined;

export function capabilities(): Promise<Capabilities> {
  return (cached ??= probe().catch((e) => {
    cached = undefined; // try again next time rather than remembering a dropped connection
    throw e;
  }));
}

async function probe(): Promise<Capabilities> {
  const table = `${config.cockatrice.prefix}_users`;
  const [ rows ] = await pool.query(
    'SELECT COLUMN_NAME AS name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
    [ table ],
  );
  const columns = new Set((rows as { name: string }[]).map((row) => row.name));
  if (columns.size === 0)
    throw new Error(`table ${table} does not exist — is this the Servatrice database, and is cockatrice.prefix right?`);

  return {
    passwordChangedDate: columns.has('passwordLastChangedDate'),
    forcePasswordChange: columns.has('force_password_change'),
    legacyBackup: columns.has('password_legacy_backup'),
  };
}
