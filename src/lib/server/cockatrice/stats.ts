import { config } from '#lib/server/config.js';
import { pool } from '#lib/server/db/index.js';

/*
 * Server statistics, read from the tables Servatrice keeps for itself. Every
 * query is cached for a while: the pages that show them are public, and the
 * numbers only move every 15 seconds anyway.
 *
 * Servatrice stamps uptime and session rows with `NOW()` (the database's local
 * time), so ages are worked out in SQL against `NOW()` too and times leave the
 * database as epoch seconds — never as DATETIMEs a timezone could shift.
 */

const prefix = config.cockatrice.prefix;
const T = {
  uptime: `\`${prefix}_uptime\``,
  sessions: `\`${prefix}_sessions\``,
  games: `\`${prefix}_games\``,
  users: `\`${prefix}_users\``,
};

const cache = new Map<string, { expires: number; value: Promise<unknown> }>();

/** Memoises `fn` under `key` for `seconds`. A failure is not remembered. */
function cached<T>(key: string, seconds: number, fn: () => Promise<T>): Promise<T> {
  const hit = cache.get(key);
  if (hit && hit.expires > Date.now())
    return hit.value as Promise<T>;

  const value = fn().catch((e) => {
    cache.delete(key);
    throw e;
  });
  cache.set(key, { expires: Date.now() + seconds * 1000, value });
  return value;
}

async function rows<T>(query: string, params: unknown[] = []): Promise<T[]> {
  const [ result ] = await pool.query(query, params);
  return result as T[];
}

export type LiveStatus = {
  online: boolean;
  /** Seconds Servatrice has been up, as of its last report. 0 when offline. */
  uptime: number;
  users: number;
  games: number;
  moderators: number;
  /** Epoch seconds of the last report, or null if it has never reported. */
  reportedAt: number | null;
  /** Seconds since that report. */
  age: number | null;
};

export function getLiveStatus(): Promise<LiveStatus> {
  return cached('live', 5, async () => {
    const [ latest ] = await rows<{ uptime: number; users: number; games: number; mods: number; at: number; age: number }>(`
      SELECT uptime, users_count AS users, games_count AS games, mods_count AS mods,
             UNIX_TIMESTAMP(timest) AS at, TIMESTAMPDIFF(SECOND, timest, NOW()) AS age
      FROM ${T.uptime} ORDER BY timest DESC LIMIT 1`);

    if (!latest)
      return { online: false, uptime: 0, users: 0, games: 0, moderators: 0, reportedAt: null, age: null };

    const online = Number(latest.age) <= config.stats.offlineAfterSeconds;
    return {
      online,
      uptime: online ? Number(latest.uptime) : 0,
      users: online ? Number(latest.users) : 0,
      games: online ? Number(latest.games) : 0,
      moderators: online ? Number(latest.mods) : 0,
      reportedAt: Number(latest.at),
      age: Number(latest.age),
    };
  });
}

export const RANGES = {
  '6h': { hours: 6, bucket: 5 * 60, label: '6 hours' },
  '24h': { hours: 24, bucket: 15 * 60, label: '24 hours' },
  '7d': { hours: 24 * 7, bucket: 60 * 60, label: '7 days' },
  '30d': { hours: 24 * 30, bucket: 6 * 60 * 60, label: '30 days' },
} as const;
export type Range = keyof typeof RANGES;

export type HistoryPoint = {
  /** Epoch seconds at the start of the bucket. */
  t: number;
  /** Peaks within the bucket — a busy moment shouldn't be averaged away. */
  users: number;
  games: number;
  /** Fraction of the bucket the server was reporting in, 0–1. */
  availability: number;
};

/**
 * Players and games over time, bucketed so a month is ~120 points rather than
 * 170,000 rows. A bucket with no reports at all is the server being down, and
 * comes back as zeros rather than a gap.
 */
export function getHistory(range: Range): Promise<HistoryPoint[]> {
  const { hours, bucket } = RANGES[range];
  return cached(`history:${range}`, 60, async () => {
    const result = await rows<{ t: number; users: number; games: number; reports: number }>(`
      SELECT FLOOR(UNIX_TIMESTAMP(timest) / ?) * ? AS t,
             MAX(users_count) AS users, MAX(games_count) AS games, COUNT(*) AS reports
      FROM ${T.uptime}
      WHERE timest >= NOW() - INTERVAL ? HOUR
      GROUP BY t ORDER BY t`, [ bucket, bucket, hours ]);

    // Servatrice reports every 15s by default; a bucket that heard from it that often was fully up.
    const expected = bucket / 15;
    const byBucket = new Map(result.map((row) => [ Number(row.t), row ]));
    const now = Math.floor(Date.now() / 1000);
    const end = Math.floor(now / bucket) * bucket;
    const start = end - hours * 3600 + bucket;

    const points: HistoryPoint[] = [];
    for (let t = start; t <= end; t += bucket) {
      const row = byBucket.get(t);
      points.push({
        t,
        users: Number(row?.users ?? 0),
        games: Number(row?.games ?? 0),
        availability: Math.min(1, Number(row?.reports ?? 0) / expected),
      });
    }
    return points;
  });
}

export type DailyPoint = {
  /** `YYYY-MM-DD`, in the database's time zone. */
  day: string;
  players: number;
  games: number;
};

/** Distinct players and games started, per day, for the last `stats.days` days. */
export function getDaily(): Promise<DailyPoint[]> {
  const days = config.stats.days;
  return cached('daily', 10 * 60, async () => {
    const [ players, games, [ today ] ] = await Promise.all([
      rows<{ day: string; n: number }>(`
        SELECT DATE_FORMAT(start_time, '%Y-%m-%d') AS day, COUNT(DISTINCT user_name) AS n
        FROM ${T.sessions}
        WHERE start_time >= CURDATE() - INTERVAL ? DAY
        GROUP BY day`, [ days - 1 ]),
      rows<{ day: string; n: number }>(`
        SELECT DATE_FORMAT(time_started, '%Y-%m-%d') AS day, COUNT(*) AS n
        FROM ${T.games}
        WHERE time_started >= CURDATE() - INTERVAL ? DAY
        GROUP BY day`, [ days - 1 ]),
      rows<{ today: string }>(`SELECT DATE_FORMAT(CURDATE(), '%Y-%m-%d') AS today`),
    ]);

    const playersByDay = new Map(players.map((row) => [ row.day, Number(row.n) ]));
    const gamesByDay = new Map(games.map((row) => [ row.day, Number(row.n) ]));

    // Walk back from the database's "today" so a quiet day shows as 0, not as missing.
    const points: DailyPoint[] = [];
    const cursor = new Date(`${today.today}T00:00:00Z`);
    cursor.setUTCDate(cursor.getUTCDate() - (days - 1));
    for (let i = 0; i < days; i++) {
      const day = cursor.toISOString().slice(0, 10);
      points.push({ day, players: playersByDay.get(day) ?? 0, games: gamesByDay.get(day) ?? 0 });
      cursor.setUTCDate(cursor.getUTCDate() + 1);
    }
    return points;
  });
}

export type CountryCount = { country: string; players: number };

/** Where the last `stats.days` days' players are from, most first. */
export function getCountries(limit = 15): Promise<{ countries: CountryCount[]; total: number }> {
  return cached(`countries:${limit}`, 10 * 60, async () => {
    const result = await rows<{ country: string; players: number }>(`
      SELECT LOWER(u.country) AS country, COUNT(DISTINCT u.id) AS players
      FROM ${T.sessions} s
      JOIN ${T.users} u ON u.name = s.user_name
      WHERE s.start_time >= NOW() - INTERVAL ? DAY AND u.country <> ''
      GROUP BY country
      ORDER BY players DESC`, [ config.stats.days ]);

    const countries = result.map((row) => ({ country: row.country, players: Number(row.players) }));
    return {
      countries: countries.slice(0, limit),
      total: countries.reduce((sum, row) => sum + row.players, 0),
    };
  });
}

export type Totals = {
  accounts: number;
  /** Accounts that started a session in the last 30 days. */
  activeAccounts: number;
  gamesToday: number;
  /** Most players online at once in the last 30 days. */
  peakUsers: number;
};

export function getTotals(): Promise<Totals> {
  return cached('totals', 10 * 60, async () => {
    const [ [ accounts ], [ active ], [ today ], [ peak ] ] = await Promise.all([
      rows<{ n: number }>(`SELECT COUNT(*) AS n FROM ${T.users} WHERE active = 1`),
      rows<{ n: number }>(`SELECT COUNT(DISTINCT user_name) AS n FROM ${T.sessions} WHERE start_time >= NOW() - INTERVAL 30 DAY`),
      rows<{ n: number }>(`SELECT COUNT(*) AS n FROM ${T.games} WHERE time_started >= CURDATE()`),
      rows<{ n: number | null }>(`SELECT MAX(users_count) AS n FROM ${T.uptime} WHERE timest >= NOW() - INTERVAL 30 DAY`),
    ]);
    return {
      accounts: Number(accounts?.n ?? 0),
      activeAccounts: Number(active?.n ?? 0),
      gamesToday: Number(today?.n ?? 0),
      peakUsers: Number(peak?.n ?? 0),
    };
  });
}
