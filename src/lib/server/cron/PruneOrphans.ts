import { eq, notExists } from 'drizzle-orm';
import type { CronJob } from '#lib/server/cron/index.ts';
import { ONE_DAY } from '#lib/server/cron/intervals.ts';
import { db, chickatrice, cockatrice } from '#lib/server/db/index.ts';
import { pruneDeckRows } from '#lib/server/cockatrice/decks.ts';
import { pruneDeckShareRows } from '#lib/server/cockatrice/shares.ts';

/**
 * Servatrice's admins delete accounts by hand, and players delete decks from
 * the client, which leaves our rows for them behind. Sign-ins tidy up the ones they trip over; this gets the rest, before
 * an id the database hands out again could pick them up.
 */
export default {
  name: 'PruneOrphans',
  interval: ONE_DAY,
  run: async () => {
    const { users } = cockatrice;
    let pruned = 0;
    for (const table of [ chickatrice.identities, chickatrice.sessions, chickatrice.nameChanges ]) {
      const [ result ] = await db.delete(table)
        .where(notExists(db.select({ id: users.id }).from(users).where(eq(users.id, table.accountId))));
      pruned += result.affectedRows;
    }
    // Decks go from the client without us hearing of it, and with their accounts.
    const decks = await pruneDeckRows();
    // After the decks, so a deleted deck's share row goes too. Servatrice deletes the expired shares themselves.
    const shares = await pruneDeckShareRows();
    if (pruned || decks || shares)
      return `pruned ${pruned} link(s) and session(s) of deleted accounts, ${decks} row(s) of deleted decks and ${shares} of expired deck shares`;
  },
} satisfies CronJob;
