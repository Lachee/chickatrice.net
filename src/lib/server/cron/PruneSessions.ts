import type { CronJob } from '#lib/server/cron/index.ts';
import { ONE_HOUR } from '#lib/server/cron/intervals.ts';
import { pruneSessions } from '#lib/server/auth/session.ts';

/** Expired sign-ins are refused already; this just stops the table growing forever. */
export default {
  name: 'PruneSessions',
  interval: ONE_HOUR,
  run: async () => {
    const pruned = await pruneSessions();
    if (pruned) return `pruned ${pruned} expired session(s)`;
  },
} satisfies CronJob;
