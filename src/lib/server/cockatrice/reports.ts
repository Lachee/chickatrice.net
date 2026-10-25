import { capabilities } from './capabilities';
import { execute, numberOrNull, rows, table } from './sql';

/*
 * Reports players file from the client (schema 36+), worked on the way
 * Servatrice's report commands do. A reporter who is offline when their report
 * is closed, or a comment added, is told at their next login: Servatrice sends
 * whatever is still `notified = 0` (`sendPendingReportNotifications`), which is
 * how what's done here reaches them.
 */

const T = {
  reports: table('reports'),
  comments: table('report_comments'),
  users: table('users'),
  replays: table('replays'),
  rooms: table('rooms'),
  games: table('games'),
  gamePlayers: table('games_players'),
};

export type ReportStatus = 'open' | 'assigned' | 'resolved' | 'dismissed';
export const REPORT_STATUSES: ReportStatus[] = [ 'open', 'assigned', 'resolved', 'dismissed' ];

/** `ReportCategories::keys()` in libcockatrice, with names for them. */
export const REPORT_CATEGORIES: Record<string, string> = {
  cheating: 'Cheating',
  bug_abuse: 'Bug abuse',
  harassment: 'Harassment',
  verbal_abuse: 'Verbal abuse',
  hate_speech: 'Hate speech',
  spam: 'Spam',
  other: 'Other',
};

export type Report = {
  id: number;
  reporterId: number | null;
  reporterName: string;
  reportedUserName: string;
  gameId: number | null;
  roomId: number | null;
  roomName: string | null;
  category: string;
  description: string;
  createdAt: number;
  status: ReportStatus;
  assignedTo: number | null;
  assignedName: string | null;
  resolvedName: string | null;
  resolutionNote: string | null;
  resolvedAt: number | null;
  replayId: number | null;
};

const reportColumns = `
  r.id, r.reporter_id AS reporterId, r.reporter_name AS reporterName, r.reported_user_name AS reportedUserName,
  r.game_id AS gameId, r.room_id AS roomId, room.name AS roomName, r.category, r.description,
  UNIX_TIMESTAMP(r.created_at) AS createdAt, r.status, r.assigned_to AS assignedTo, assigned.name AS assignedName,
  resolver.name AS resolvedName, r.resolution_note AS resolutionNote, UNIX_TIMESTAMP(r.resolution_time) AS resolvedAt,
  (SELECT id FROM ${T.replays} WHERE id_game = r.game_id ORDER BY id DESC LIMIT 1) AS replayId`;

const reportFrom = `
  FROM ${T.reports} r
  LEFT JOIN ${T.users} assigned ON assigned.id = r.assigned_to
  LEFT JOIN ${T.users} resolver ON resolver.id = r.resolved_by
  LEFT JOIN ${T.rooms} room ON room.id = r.room_id`;

function toReport(row: Record<string, unknown>): Report {
  return {
    id: Number(row.id),
    reporterId: numberOrNull(row.reporterId),
    reporterName: String(row.reporterName),
    reportedUserName: String(row.reportedUserName),
    gameId: numberOrNull(row.gameId),
    roomId: numberOrNull(row.roomId),
    roomName: (row.roomName as string | null) ?? null,
    category: String(row.category),
    description: String(row.description ?? ''),
    createdAt: Number(row.createdAt),
    status: row.status as ReportStatus,
    assignedTo: numberOrNull(row.assignedTo),
    assignedName: (row.assignedName as string | null) ?? null,
    resolvedName: (row.resolvedName as string | null) ?? null,
    resolutionNote: (row.resolutionNote as string | null) ?? null,
    resolvedAt: numberOrNull(row.resolvedAt),
    replayId: numberOrNull(row.replayId),
  };
}

async function requireReports() {
  if (!(await capabilities()).reports) throw new Error('this schema has no reports table');
}

export type ReportFilter = 'unresolved' | 'mine' | ReportStatus | 'all';

export async function listReports({ filter = 'unresolved', userName, staffId, limit = 50, offset = 0 }: {
  filter?: ReportFilter;
  /** Only reports about this player. */
  userName?: string;
  /** Who "mine" means. */
  staffId?: number;
  limit?: number;
  offset?: number;
}): Promise<{ reports: Report[]; total: number }> {
  await requireReports();
  const where: string[] = [];
  const params: unknown[] = [];
  if (filter === 'unresolved') where.push(`r.status IN ('open', 'assigned')`);
  else if (filter === 'mine') {
    where.push(`r.status = 'assigned' AND r.assigned_to = ?`);
    params.push(staffId ?? 0);
  } else if (filter !== 'all') {
    where.push('r.status = ?');
    params.push(filter);
  }
  if (userName) {
    where.push('r.reported_user_name = ?');
    params.push(userName);
  }
  const clause = where.length ? `WHERE ${where.join(' AND ')}` : '';

  const [ result, [ count ] ] = await Promise.all([
    rows<Record<string, unknown>>(`SELECT ${reportColumns} ${reportFrom} ${clause} ORDER BY r.created_at DESC LIMIT ? OFFSET ?`,
      [ ...params, limit, offset ]),
    rows<{ n: number }>(`SELECT COUNT(*) AS n FROM ${T.reports} r ${clause}`, params),
  ]);
  return { reports: result.map(toReport), total: Number(count?.n ?? 0) };
}

export type ReportComment = {
  id: number;
  authorName: string;
  text: string;
  createdAt: number;
  isModerator: boolean;
};

export type ReportGame = {
  id: number;
  roomName: string | null;
  description: string | null;
  creatorName: string | null;
  gameTypes: string | null;
  started: number | null;
  finished: number | null;
  players: string[];
};

export type ReportDetail = Report & {
  chatLog: string | null;
  comments: ReportComment[];
  /** What Servatrice stored about the game it came from, once that game ended. */
  game: ReportGame | null;
};

export async function getReport(id: number): Promise<ReportDetail | null> {
  await requireReports();
  const [ row ] = await rows<Record<string, unknown>>(`SELECT ${reportColumns}, r.chat_log AS chatLog ${reportFrom} WHERE r.id = ?`, [ id ]);
  if (!row) return null;

  const report = toReport(row);
  const [ comments, game ] = await Promise.all([
    rows<Record<string, unknown>>(`
      SELECT id, author_name AS authorName, comment_text AS text, UNIX_TIMESTAMP(created_at) AS createdAt, is_moderator AS isModerator
      FROM ${T.comments} WHERE report_id = ? ORDER BY created_at, id`, [ id ]),
    report.gameId ? getGame(report.gameId) : Promise.resolve(null),
  ]);
  return {
    ...report,
    chatLog: (row.chatLog as string | null) ?? null,
    comments: comments.map((c) => ({
      id: Number(c.id),
      authorName: String(c.authorName),
      text: String(c.text ?? ''),
      createdAt: Number(c.createdAt),
      isModerator: Number(c.isModerator) === 1,
    })),
    game,
  };
}

async function getGame(id: number): Promise<ReportGame | null> {
  const [ [ game ], players ] = await Promise.all([
    rows<Record<string, unknown>>(`
      SELECT id, room_name AS roomName, descr AS description, creator_name AS creatorName, game_types AS gameTypes,
        UNIX_TIMESTAMP(time_started) AS started, UNIX_TIMESTAMP(time_finished) AS finished
      FROM ${T.games} WHERE id = ?`, [ id ]),
    rows<{ name: string }>(`SELECT player_name AS name FROM ${T.gamePlayers} WHERE id_game = ?`, [ id ]),
  ]);
  if (!game) return null;
  return {
    id: Number(game.id),
    roomName: (game.roomName as string | null) || null,
    description: (game.description as string | null) || null,
    creatorName: (game.creatorName as string | null) || null,
    gameTypes: (game.gameTypes as string | null) || null,
    started: numberOrNull(game.started),
    finished: numberOrNull(game.finished),
    players: players.map((p) => p.name),
  };
}

/** Takes an open report, as `cmdReportAssign` does. False if someone else got there first. */
export async function assignReport(id: number, staffId: number): Promise<boolean> {
  await requireReports();
  const result = await execute(`UPDATE ${T.reports} SET status = 'assigned', assigned_to = ? WHERE id = ? AND status = 'open'`, [ staffId, id ]);
  return result.affectedRows > 0;
}

/**
 * Closes a report, as `cmdReportResolve` does. `notified` is left at 0 so
 * Servatrice tells the reporter at their next login — unless the staff member
 * closing it is the reporter.
 */
export async function resolveReport(id: number, staffId: number, note: string, dismissed: boolean): Promise<boolean> {
  await requireReports();
  const result = await execute(`
    UPDATE ${T.reports}
    SET status = ?, resolution_note = ?, resolution_time = NOW(), resolved_by = ?, notified = (reporter_id <=> ?)
    WHERE id = ? AND status IN ('open', 'assigned')`,
  [ dismissed ? 'dismissed' : 'resolved', note, staffId, staffId, id ]);
  return result.affectedRows > 0;
}

/** A staff comment, as `cmdReportAddComment` does; delivered to the reporter and assignee at their next login. */
export async function addReportComment(id: number, author: { id: number; name: string }, text: string): Promise<boolean> {
  await requireReports();
  const result = await execute(`
    INSERT INTO ${T.comments} (report_id, author_name, author_id, comment_text, created_at, is_moderator)
    SELECT id, ?, ?, ?, NOW(), 1 FROM ${T.reports} WHERE id = ? AND status IN ('open', 'assigned')`,
  [ author.name, author.id, text, id ]);
  return result.affectedRows > 0;
}

export type ReportStats = {
  total: number;
  pending: number;
  last7d: number;
  last30d: number;
  avgResolutionHours: number | null;
  categories: { label: string; count: number }[];
  topReported: { label: string; count: number }[];
};

/** `cmdReportStats`, trimmed to what the reports page shows. */
export async function getReportStats(): Promise<ReportStats> {
  await requireReports();
  const [ [ overview ], [ avg ], categories, topReported ] = await Promise.all([
    rows<Record<string, unknown>>(`
      SELECT COUNT(*) AS total, SUM(status IN ('open', 'assigned')) AS pending,
        SUM(created_at >= NOW() - INTERVAL 7 DAY) AS last7d, SUM(created_at >= NOW() - INTERVAL 30 DAY) AS last30d
      FROM ${T.reports}`),
    rows<{ hours: number | null }>(`
      SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolution_time)) AS hours FROM ${T.reports}
      WHERE status IN ('resolved', 'dismissed') AND resolution_time IS NOT NULL`),
    rows<{ label: string; n: number }>(`SELECT category AS label, COUNT(*) AS n FROM ${T.reports} GROUP BY category ORDER BY n DESC LIMIT 10`),
    rows<{ label: string; n: number }>(`
      SELECT reported_user_name AS label, COUNT(*) AS n FROM ${T.reports}
      WHERE created_at >= NOW() - INTERVAL 30 DAY GROUP BY reported_user_name ORDER BY n DESC LIMIT 10`),
  ]);
  return {
    total: Number(overview?.total ?? 0),
    pending: Number(overview?.pending ?? 0),
    last7d: Number(overview?.last7d ?? 0),
    last30d: Number(overview?.last30d ?? 0),
    avgResolutionHours: numberOrNull(avg?.hours),
    categories: categories.map((row) => ({ label: REPORT_CATEGORIES[row.label.toLowerCase()] ?? row.label, count: Number(row.n) })),
    topReported: topReported.map((row) => ({ label: row.label, count: Number(row.n) })),
  };
}
