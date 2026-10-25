import { and, desc, eq, isNotNull, lt, or, sql } from 'drizzle-orm';
import { building, dev } from '$app/env';
import { db, pool, chickatrice } from '#lib/server/db/index.ts';
import { withLock } from '#lib/server/db/lock.ts';

import PruneOrphans from '#lib/server/cron/PruneOrphans.ts';
import PruneSessions from '#lib/server/cron/PruneSessions.ts';

export type CronJob = {
  name: string;
  interval: number;
  /**
   * Returns what it did, in a line, when there is something worth saying: it is
   * logged and kept with the run for the admin page. A tick that found nothing
   * to do should say nothing.
   */
  run: () => Promise<string | void>;
};

/** One finished run, as `chickatrice_cron_runs` keeps it. */
export type CronRun = {
  /** When it started, epoch seconds. */
  at: number;
  durationMs: number;
  ok: boolean;
  /** Started from the admin page rather than by the timer. */
  manual: boolean;
  summary: string | null;
  error: string | null;
};

/** A job and what is known about it right now, for the admin page. */
export type CronJobStatus = {
  name: string;
  interval: number;
  /** True while its lock is held, by this instance or any other. */
  running: boolean;
  lastRun: CronRun | null;
  /** Runs that said something or failed, newest first: the last `HISTORY_LENGTH`. */
  history: CronRun[];
};

const { cronRuns } = chickatrice;

/** Runs that said something kept per job for the admin page. */
const HISTORY_LENGTH = 20;
/** How long a run is kept at all. */
const RUN_HISTORY_DAYS = 30;

const jobs: CronJob[] = [
  PruneOrphans,
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
      execute(job, false).catch((e) => console.error(`[cron] ${job.name}`, e));
    // First run is one interval after boot, not at boot: a restart shouldn't
    // set off every job at once. The admin page can run one sooner.
    setInterval(tick, job.interval).unref(); // don't hold the process open
  }
}

/**
 * Every job with its last recorded run and whether one is in flight.
 *
 * There is deliberately no "next run": the schedule is a `setInterval` per
 * instance, started whenever that instance booted, so there is no shared clock
 * to read a due time off. `lastRun` plus `interval` is what is actually true.
 */
export async function getJobStatuses(): Promise<CronJobStatus[]> {
  return await Promise.all(jobs.map(async (job) => {
    const [ [ last ], history, [ locks ] ] = await Promise.all([
      db.select().from(cronRuns).where(eq(cronRuns.job, job.name)).orderBy(desc(cronRuns.id)).limit(1),
      db.select().from(cronRuns)
        .where(and(eq(cronRuns.job, job.name), or(isNotNull(cronRuns.summary), isNotNull(cronRuns.error))))
        .orderBy(desc(cronRuns.id))
        .limit(HISTORY_LENGTH),
      pool.query('SELECT IS_USED_LOCK(?) AS holder', [ lockName(job.name) ]),
    ]);
    return {
      name: job.name,
      interval: job.interval,
      running: (locks as { holder: number | null }[])[0]?.holder != null,
      lastRun: last ? toRun(last) : null,
      history: history.map(toRun),
    };
  }));
}

/**
 * Runs one job now, by name. It takes the same lock the timer does, so it never
 * overlaps a scheduled run (or a manual one on another instance) and is skipped
 * instead, which `ran: false` reports. Under `dev` this is the only way a job
 * runs at all, as `start()` sets no timers there.
 *
 * A job that throws is reported rather than rethrown: the caller is staff
 * pressing a button, and the message is more use to them than a 500.
 */
export async function runJob(name: string): Promise<{ ran: boolean; summary?: string; error?: string } | null> {
  const job = jobs.find((j) => j.name === name);
  if (!job) return null;

  let summary: string | undefined;
  try {
    const ran = await execute(job, true, (said) => (summary = said));
    return { ran, summary };
  } catch (e) {
    return { ran: true, summary, error: e instanceof Error ? e.message : String(e) };
  }
}

const lockName = (name: string) => `chickatrice-cron-${name}`;

/**
 * Runs a job under a MySQL named lock, so with several replicas only one of
 * them does it each tick; the others find the lock taken and skip, which
 * returning `false` reports. The run is recorded either way it ends.
 */
async function execute(job: CronJob, manual: boolean, onSummary?: (summary: string) => void): Promise<boolean> {
  return await withLock(pool, lockName(job.name), 0, async () => {
    const startedAt = new Date();
    try {
      const summary = (await job.run()) || undefined;
      if (summary) {
        onSummary?.(summary);
        console.log(`[cron] ${job.name}: ${summary}`);
      }
      await record(job, { startedAt, manual, ok: true, summary });
    } catch (e) {
      await record(job, { startedAt, manual, ok: false, error: e instanceof Error ? e.message : String(e) });
      throw e;
    }
  });
}

/** Never throws: the job already ran, and losing its record shouldn't fail it. */
async function record(job: CronJob, run: { startedAt: Date; manual: boolean; ok: boolean; summary?: string; error?: string }) {
  try {
    await db.insert(cronRuns).values({
      job: job.name,
      startedAt: run.startedAt,
      durationMs: Date.now() - run.startedAt.getTime(),
      ok: run.ok,
      manual: run.manual,
      summary: run.summary?.slice(0, 65535) ?? null,
      error: run.error?.slice(0, 65535) ?? null,
    });
    await db.delete(cronRuns)
      .where(and(eq(cronRuns.job, job.name), lt(cronRuns.startedAt, sql`UTC_TIMESTAMP() - INTERVAL ${RUN_HISTORY_DAYS} DAY`)));
  } catch (e) {
    console.error(`[cron] could not record a run of ${job.name}`, e);
  }
}

function toRun(row: typeof cronRuns.$inferSelect): CronRun {
  return {
    at: Math.floor(row.startedAt.getTime() / 1000),
    durationMs: row.durationMs,
    ok: row.ok,
    manual: row.manual,
    summary: row.summary,
    error: row.error,
  };
}
