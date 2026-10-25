import { config } from '#lib/server/config.ts';
import { capabilities } from './capabilities';
import { cached, RANGES, type Range } from './stats';
import { numberOrNull, rows, table, utcEpoch } from './sql';

/*
 * The admin dashboard's numbers and the list of who is connected. Staff-only,
 * but cached all the same: several moderators leaving the dashboard open
 * shouldn't mean several sets of queries every few seconds.
 */

const T = {
  uptime: table('uptime'),
  sessions: table('sessions'),
  users: table('users'),
  analytics: table('user_analytics'),
  bans: table('bans'),
  warnings: table('warnings'),
  reports: table('reports'),
};

export type OnlinePlayer = {
  sessionId: number;
  name: string;
  ip: string;
  clientId: string;
  connectionType: string | null;
  /** Epoch seconds the session started. */
  since: number;
  /** Null for a guest, which has no account. */
  accountId: number | null;
  admin: number;
  country: string;
  privlevel: string;
  clientVersion: string | null;
};

export type OnlineList = {
  players: OnlinePlayer[];
  /** Whether Servatrice is still reporting in; if not, these are sessions it left open when it stopped. */
  serverOnline: boolean;
  /** Who it last counted as staff online, from its own status report. */
  moderators: string[];
  /** Its own count, which includes connections that haven't logged in yet. */
  reportedUsers: number;
};

/**
 * Open sessions on this server. Servatrice closes every session of its own when
 * it starts (`clearSessionTables`), so an open one can't be older than its
 * uptime: that bound lets the query use the start-time index rather than scan
 * every session ever.
 */
export function getOnlinePlayers(): Promise<OnlineList> {
  return cached('admin:online', 10, async () => {
    const [ status ] = await rows<{ uptime: number; age: number; users: number; mods: string | null }>(`
      SELECT uptime, users_count AS users, mods_list AS mods, TIMESTAMPDIFF(SECOND, timest, NOW()) AS age
      FROM ${T.uptime} WHERE id_server = ? ORDER BY timest DESC LIMIT 1`, [ config.cockatrice.serverId ]);
    if (!status)
      return { players: [], serverOnline: false, moderators: [], reportedUsers: 0 };

    // A few minutes' slack for the time between starting and the first report.
    const window = Number(status.age) + Number(status.uptime) + 300;
    const result = await rows<Record<string, unknown>>(`
      SELECT s.id AS sessionId, s.user_name AS name, s.ip_address AS ip, s.clientid AS clientId,
        s.connection_type AS connectionType, UNIX_TIMESTAMP(s.start_time) AS since,
        u.id AS accountId, u.admin, LOWER(u.country) AS country, u.privlevel, a.client_ver AS clientVersion
      FROM ${T.sessions} s
      LEFT JOIN ${T.users} u ON u.name = s.user_name
      LEFT JOIN ${T.analytics} a ON a.id = u.id
      WHERE s.start_time >= NOW() - INTERVAL ? SECOND AND s.end_time IS NULL AND s.id_server = ?
      ORDER BY s.start_time`, [ window, config.cockatrice.serverId ]);

    return {
      serverOnline: Number(status.age) <= config.stats.offlineAfterSeconds,
      moderators: (status.mods ?? '').split(',').map((name) => name.trim()).filter(Boolean),
      reportedUsers: Number(status.users),
      players: result.map((row) => ({
        sessionId: Number(row.sessionId),
        name: String(row.name),
        ip: String(row.ip ?? ''),
        clientId: String(row.clientId ?? ''),
        connectionType: (row.connectionType as string | null) ?? null,
        since: Number(row.since),
        accountId: numberOrNull(row.accountId),
        admin: Number(row.admin ?? 0),
        country: String(row.country ?? ''),
        privlevel: String(row.privlevel ?? 'NONE'),
        clientVersion: (row.clientVersion as string | null) || null,
      })),
    };
  });
}

export type TrafficPoint = {
  t: number;
  /** Average bytes per second over the bucket, received from clients. */
  rx: number;
};

/**
 * Bytes received, bucketed like `getHistory`. Each uptime row holds the bytes
 * since the one before. Servatrice's sockets emit `incTxBytes` but nothing is
 * connected to it, so `tx_bytes` is always 0 and isn't worth reading.
 */
export function getTraffic(range: Range): Promise<TrafficPoint[]> {
  const { hours, bucket } = RANGES[range];
  return cached(`admin:traffic:${range}`, 60, async () => {
    const result = await rows<{ t: number; rx: number }>(`
      SELECT FLOOR(UNIX_TIMESTAMP(timest) / ?) * ? AS t, SUM(rx_bytes) AS rx
      FROM ${T.uptime}
      WHERE timest >= NOW() - INTERVAL ? HOUR
      GROUP BY t ORDER BY t`, [ bucket, bucket, hours ]);

    const byBucket = new Map(result.map((row) => [ Number(row.t), row ]));
    const now = Math.floor(Date.now() / 1000);
    const end = Math.floor(now / bucket) * bucket;
    const points: TrafficPoint[] = [];
    for (let t = end - hours * 3600 + bucket; t <= end; t += bucket) {
      const row = byBucket.get(t);
      const span = t === end ? Math.max(1, now - end) : bucket;
      points.push({ t, rx: Math.round(Number(row?.rx ?? 0) / span) });
    }
    return points;
  });
}

/** New accounts per day (UTC), for the last `stats.days` days. */
export function getRegistrations(): Promise<{ day: string; v: number }[]> {
  const days = config.stats.days;
  return cached('admin:registrations', 10 * 60, async () => {
    // registrationDate isn't indexed, so this reads every account; hence the ten minutes.
    const result = await rows<{ day: string; n: number }>(`
      SELECT DATE_FORMAT(registrationDate, '%Y-%m-%d') AS day, COUNT(*) AS n
      FROM ${T.users}
      WHERE registrationDate >= UTC_DATE() - INTERVAL ? DAY
      GROUP BY day`, [ days - 1 ]);
    const byDay = new Map(result.map((row) => [ row.day, Number(row.n) ]));

    const points: { day: string; v: number }[] = [];
    const cursor = new Date();
    cursor.setUTCHours(0, 0, 0, 0);
    cursor.setUTCDate(cursor.getUTCDate() - (days - 1));
    for (let i = 0; i < days; i++) {
      const day = cursor.toISOString().slice(0, 10);
      points.push({ day, v: byDay.get(day) ?? 0 });
      cursor.setUTCDate(cursor.getUTCDate() + 1);
    }
    return points;
  });
}

export type Share = { label: string; count: number };

/** Client versions of everyone who logged in in the last 30 days, most used first. */
export function getClientVersions(): Promise<{ versions: Share[]; total: number }> {
  return cached('admin:versions', 10 * 60, async () => {
    const result = await rows<{ label: string | null; n: number }>(`
      SELECT client_ver AS label, COUNT(*) AS n FROM ${T.analytics}
      WHERE last_login >= NOW() - INTERVAL 30 DAY
      GROUP BY client_ver ORDER BY n DESC`);
    const versions = result.map((row) => ({ label: row.label || 'Unknown', count: Number(row.n) }));
    return { versions, total: versions.reduce((sum, row) => sum + row.count, 0) };
  });
}

/** Desktop (TCP) against websocket connections over the last 7 days. */
export function getConnectionTypes(): Promise<{ types: Share[]; total: number }> {
  return cached('admin:connections', 10 * 60, async () => {
    const result = await rows<{ label: string | null; n: number }>(`
      SELECT connection_type AS label, COUNT(*) AS n FROM ${T.sessions}
      WHERE start_time >= NOW() - INTERVAL 7 DAY
      GROUP BY connection_type ORDER BY n DESC`);
    const names: Record<string, string> = { tcp: 'Desktop (TCP)', websocket: 'WebSocket' };
    const types = result.map((row) => ({ label: names[row.label ?? ''] ?? 'Unknown', count: Number(row.n) }));
    return { types, total: types.reduce((sum, row) => sum + row.count, 0) };
  });
}

export type ModerationCounts = {
  activeBans: number;
  bansThisWeek: number;
  warningsThisWeek: number;
  /** Null when this schema has no reports. */
  openReports: number | null;
  /** Epoch seconds of the newest account. */
  newestAccount: number | null;
};

export function getModerationCounts(): Promise<ModerationCounts> {
  return cached('admin:counts', 30, async () => {
    const caps = await capabilities();
    const [ [ bans ], [ warnings ], reports, [ newest ] ] = await Promise.all([
      rows<{ active: number; week: number }>(`
        SELECT SUM(minutes = 0 OR time_from + INTERVAL minutes MINUTE > NOW()) AS active,
               SUM(time_from >= NOW() - INTERVAL 7 DAY) AS week
        FROM ${T.bans}`),
      rows<{ n: number }>(`SELECT COUNT(*) AS n FROM ${T.warnings} WHERE time_of >= NOW() - INTERVAL 7 DAY`),
      caps.reports
        ? rows<{ n: number }>(`SELECT COUNT(*) AS n FROM ${T.reports} WHERE status IN ('open', 'assigned')`)
        : Promise.resolve(null),
      rows<{ at: number | null }>(`SELECT ${utcEpoch('registrationDate')} AS at FROM ${T.users} ORDER BY id DESC LIMIT 1`),
    ]);
    return {
      activeBans: Number(bans?.active ?? 0),
      bansThisWeek: Number(bans?.week ?? 0),
      warningsThisWeek: Number(warnings?.n ?? 0),
      openReports: reports ? Number(reports[0]?.n ?? 0) : null,
      newestAccount: numberOrNull(newest?.at),
    };
  });
}
