import { building, dev } from '$app/env';
import { pool } from '#lib/server/db/index.js';
import { withLock } from '#lib/server/db/lock.js';

import PruneSessions from '#lib/server/cron/PruneSessions.js';

export type CronJob = {
  name: string;
  interval: number;
  /**
   * Returns what it did, in a line, when there is something worth saying: it is
   * logged. A tick that found nothing to do should say nothing.
   */
  run: () => Promise<string | void>;
};

const jobs: CronJob[] = [
  PruneSessions,
];

export function start() {
  if (building || dev) // dev HMR would stack timers
    return;

  if ((globalThis as any).__cronStarted)
    return;

  console.log(`[cron] Starting cron jobs`);
  (globalThis as any).__cronStarted = true;
  for (const job of jobs) {
    const tick = () =>
      execute(job).catch((e) => console.error(`[cron] ${job.name}`, e));
    // First run is one interval after boot, not at boot: a restart shouldn't
    // set off every job at once.
    setInterval(tick, job.interval).unref(); // don't hold the process open
  }
}

/**
 * Runs a job under a MySQL named lock, so with several replicas only one of
 * them does it each tick; the others find the lock taken and skip.
 */
async function execute(job: CronJob) {
  await withLock(pool, `chickatrice-cron-${job.name}`, 0, async () => {
    const summary = await job.run();
    if (summary) console.log(`[cron] ${job.name}: ${summary}`);
  });
}
