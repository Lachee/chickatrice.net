import { config } from '#lib/server/config.ts';
import { pool } from '#lib/server/db/index.ts';

/**
 * Which optional `cockatrice_users` columns and tables this database has.
 * Servatrice adds them as its schema version climbs, and this site has to work
 * against whichever version the server runs — so rather than declaring them, it asks.
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
  /** `adminnotes` (schema 30+): staff notes on the account. */
  adminNotes: boolean;
  /** The `reports` and `report_comments` tables (schema 36+): reports players file from the client. */
  reports: boolean;
  /** The `card_art_name_rules` table (schema 35+): which cards may be used as profile art. */
  cardArtRules: boolean;
  /**
   * `is_public` on `decklist_files` and `decklist_folders` (schema 37+), with the
   * preview columns (`banner_card_name`, `tags`, ...) that came alongside: decks
   * other players can browse from the client.
   */
  publicDecks: boolean;
  /**
   * The `deck_share` and `deck_share_item` tables (schema 37+): decks fetched by
   * token, which `cockatrice://opendeck` links open in the client.
   */
  deckShares: boolean;
};

let cached: Promise<Capabilities> | undefined;

export function capabilities(): Promise<Capabilities> {
  return (cached ??= probe().catch((e) => {
    cached = undefined; // try again next time rather than remembering a dropped connection
    throw e;
  }));
}

async function probe(): Promise<Capabilities> {
  const prefix = config.cockatrice.prefix;
  const table = `${prefix}_users`;
  const [ [ rows ], [ tableRows ], [ deckRows ] ] = await Promise.all([
    pool.query(
      'SELECT COLUMN_NAME AS name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
      [ table ],
    ),
    pool.query(
      'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?)',
      [ [ 'reports', 'report_comments', 'card_art_name_rules', 'deck_share', 'deck_share_item' ].map((name) => `${prefix}_${name}`) ],
    ),
    pool.query(
      `SELECT TABLE_NAME AS name FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?) AND COLUMN_NAME = 'is_public'`,
      [ [ 'decklist_files', 'decklist_folders' ].map((name) => `${prefix}_${name}`) ],
    ),
  ]);
  const columns = new Set((rows as { name: string }[]).map((row) => row.name));
  if (columns.size === 0)
    throw new Error(`table ${table} does not exist — is this the Servatrice database, and is cockatrice.prefix right?`);
  const tables = new Set((tableRows as { name: string }[]).map((row) => row.name.slice(prefix.length + 1)));

  return {
    passwordChangedDate: columns.has('passwordLastChangedDate'),
    forcePasswordChange: columns.has('force_password_change'),
    legacyBackup: columns.has('password_legacy_backup'),
    adminNotes: columns.has('adminnotes'),
    reports: tables.has('reports') && tables.has('report_comments'),
    cardArtRules: tables.has('card_art_name_rules'),
    publicDecks: (deckRows as unknown[]).length === 2,
    deckShares: tables.has('deck_share') && tables.has('deck_share_item'),
  };
}
