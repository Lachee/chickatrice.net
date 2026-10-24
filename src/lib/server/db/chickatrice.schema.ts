import { datetime, index, int, mysqlTable, serial, uniqueIndex, varchar } from 'drizzle-orm/mysql-core';

/*
 * This site's own tables. Everything it adds to the Servatrice database is
 * prefixed `chickatrice_` so it can never collide with Servatrice's tables,
 * and drizzle-kit only ever looks at these (`tablesFilter` in drizzle.config.ts).
 *
 * Account ids reference `cockatrice_users.id` without a foreign key: that table
 * belongs to Servatrice, which may be configured with another prefix and drops
 * users without knowing these tables exist. Orphans are cleaned up in code.
 *
 * Times are UTC; compare against `UTC_TIMESTAMP()`, never `NOW()`.
 */

/** A provider sign-in linked to a Cockatrice account. */
export const identities = mysqlTable('chickatrice_identities', {
  id: serial('id').primaryKey(),
  accountId: int('account_id', { unsigned: true }).notNull(),
  /** `id` of the provider in config.yaml. */
  provider: varchar('provider', { length: 64 }).notNull(),
  /**
   * The provider's stable identifier for the user (the `sub` claim, usually).
   * Case-sensitive: 0000_initial.sql gives it `utf8mb4_bin` by hand, which
   * drizzle-kit can't express (or see, so it won't try to undo it).
   */
  subject: varchar('subject', { length: 255 }).notNull(),
  /** What the provider last said, for display only; refreshed on each sign-in. */
  username: varchar('username', { length: 255 }),
  email: varchar('email', { length: 255 }),
  image: varchar('image', { length: 2048 }),
  createdAt: datetime('created_at', { mode: 'date' }).notNull(),
  lastLoginAt: datetime('last_login_at', { mode: 'date' }).notNull(),
}, (table) => [
  uniqueIndex('chickatrice_identities_provider_subject').on(table.provider, table.subject),
  // One link per provider per account keeps "which Discord is this?" answerable.
  uniqueIndex('chickatrice_identities_account_provider').on(table.accountId, table.provider),
]);

/** A browser signed in to the site. Only a hash of the cookie's token is kept. */
export const sessions = mysqlTable('chickatrice_sessions', {
  /** SHA-256 of the cookie token, hex. */
  id: varchar('id', { length: 64 }).primaryKey(),
  accountId: int('account_id', { unsigned: true }).notNull(),
  createdAt: datetime('created_at', { mode: 'date' }).notNull(),
  expiresAt: datetime('expires_at', { mode: 'date' }).notNull(),
  /** How the session was opened: `password` or a provider id. */
  method: varchar('method', { length: 64 }).notNull(),
  userAgent: varchar('user_agent', { length: 255 }),
}, (table) => [
  index('chickatrice_sessions_account').on(table.accountId),
  index('chickatrice_sessions_expires').on(table.expiresAt),
]);
