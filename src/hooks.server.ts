import { sequence, type Handle, type ServerInit } from '@sveltejs/kit/hooks';
import { building } from '$app/env';
import { config } from '#lib/server/config.ts';
import { migrateDatabase } from '#lib/server/db/migrate.ts';
import { capabilities } from '#lib/server/cockatrice/capabilities.ts';
import { findAccountById } from '#lib/server/cockatrice/accounts.ts';
import { handle as authjs } from '#lib/server/auth/index.ts';
import { start as startCron } from '#lib/server/cron/index.ts';

export const init: ServerInit = async () => {
  if (building) return;
  await migrateDatabase(config.db.url);
  await capabilities();
  startCron();
};

const resolveAccount: Handle = async ({ event, resolve }) => {
  const session = await event.locals.auth();
  event.locals.account = session?.user ? await findAccountById(Number(session.user.id)) : null;
  return resolve(event);
};

export const handle = sequence(authjs, resolveAccount);
