import { char, customType, datetime, int, mysqlEnum, mysqlTable, text, tinyint, varchar } from 'drizzle-orm/mysql-core';
import { config } from '#lib/server/config.js';

/*
 * == NOTICE ==
 * Servatrice manages these tables itself, so they are deliberately outside drizzle-kit's `*.schema.ts` glob.
 * No migration ever creates or alters them in this file.
 *
 * All custom tables should be in a seperate .schema.ts file.
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

