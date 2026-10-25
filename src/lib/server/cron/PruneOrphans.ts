import { eq, notExists } from 'drizzle-orm';
import type { CronJob } from '#lib/server/cron/index.ts';
import { ONE_DAY } from '#lib/server/cron/intervals.ts';
import { db, chickatrice, cockatrice } from '#lib/server/db/index.ts';

/**
 * Servatrice's admins delete accounts by hand, which leaves our rows for them
 * behind. Sign-ins tidy up the ones they trip over; this gets the rest, before
 * an id the database hands out again could pick them up.
 */
export default {
  name: 'PruneOrphans',
  interval: ONE_DAY,
  run: async () => {
    const { users } = cockatrice;
    let pruned = 0;
    for (const table of [ chickatrice.identities, chickatrice.sessions ]) {
      const [ result ] = await db.delete(table)
        .where(notExists(db.select({ id: users.id }).from(users).where(eq(users.id, table.accountId))));
      pruned += result.affectedRows;
    }
    if (pruned) return `pruned ${pruned} link(s) and session(s) of deleted accounts`;
  },
} satisfies CronJob;
