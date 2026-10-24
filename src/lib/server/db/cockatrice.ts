import { char, customType, datetime, int, mysqlEnum, mysqlTable, text, tinyint, varchar } from 'drizzle-orm/mysql-core';
import { config } from '#lib/server/config.js';

/*
 * Servatrice's own tables (servatrice/servatrice.sql upstream). Servatrice owns
 * them: they are deliberately outside drizzle-kit's `*.schema.ts` glob, so no
 * migration ever creates or alters them. Only the columns this site reads or
 * writes are declared, and only ones every recent schema version has — newer
 * columns (password_legacy_backup, ...) are probed for at runtime instead, see
 * `#lib/server/cockatrice/capabilities`. The stats read the uptime, session
 * and game tables with plain SQL (`#lib/server/cockatrice/stats`).
 */

const table = (name: string) => `${config.cockatrice.prefix}_${name}`;

const mediumblob = customType<{ data: Buffer; driverData: Buffer }>({
  dataType: () => 'mediumblob',
});

export const users = mysqlTable(table('users'), {
  id: int('id', { unsigned: true }).primaryKey().autoincrement(),
  admin: tinyint('admin').notNull(),
  name: varchar('name', { length: 35 }).notNull(),
  realname: varchar('realname', { length: 255 }).notNull(),
  passwordSha512: varchar('password_sha512', { length: 255 }).notNull(),
  email: varchar('email', { length: 255 }).notNull(),
  country: char('country', { length: 2 }).notNull(),
  avatarBmp: mediumblob('avatar_bmp').notNull(),
  registrationDate: datetime('registrationDate', { mode: 'date' }).notNull(),
  active: tinyint('active').notNull(),
  clientid: varchar('clientid', { length: 15 }).notNull(),
  adminnotes: text('adminnotes').notNull(),
  privlevel: mysqlEnum('privlevel', [ 'NONE', 'VIP', 'DONATOR' ]).notNull(),
  privlevelStartDate: datetime('privlevelStartDate', { mode: 'date' }).notNull(),
  privlevelEndDate: datetime('privlevelEndDate', { mode: 'date' }).notNull(),
});

