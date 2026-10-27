import { randomInt } from 'node:crypto';
import { isIP } from 'node:net';
import { sql } from 'drizzle-orm';
import { config } from '#lib/server/config.ts';
import { chickatrice, db, pool } from '#lib/server/db/index.ts';
import { likeEscape } from '#lib/server/admin/form.ts';
import { ADJUSTABLE_FLAGS } from '#lib/staff.ts';
import { capabilities } from './capabilities';
import { setPassword } from './accounts';
import { execute, numberOrNull, rows, table, utcEpoch } from './sql';

/*
 * What Servatrice's moderator and admin commands do, done to its tables
 * directly (serversocketinterface.cpp has the originals). The server never
 * hears about it, so nothing here can reach a connected player: a ban stops
 * their next login, not the session they're in, and a warning or demotion
 * isn't shown to them. The admin pages say so where it matters.
 */

const T = {
  users: table('users'),
  analytics: table('user_analytics'),
  sessions: table('sessions'),
  bans: table('bans'),
  warnings: table('warnings'),
  activation: table('activation_emails'),
};

export type Privilege = 'NONE' | 'VIP' | 'DONATOR';
export const PRIVILEGES: Privilege[] = [ 'NONE', 'VIP', 'DONATOR' ];

export type PlayerSummary = {
  id: number;
  name: string;
  email: string;
  country: string;
  admin: number;
  active: boolean;
  privlevel: Privilege;
  /** Epoch seconds. */
  registeredAt: number | null;
  lastLogin: number | null;
  clientVersion: string | null;
};

const summaryColumns = `
  u.id, u.name, u.email, LOWER(u.country) AS country, u.admin, u.active, u.privlevel,
  ${utcEpoch('u.registrationDate')} AS registeredAt,
  UNIX_TIMESTAMP(a.last_login) AS lastLogin, a.client_ver AS clientVersion`;

type SummaryRow = Record<keyof PlayerSummary, unknown>;

function toSummary(row: SummaryRow): PlayerSummary {
  return {
    id: Number(row.id),
    name: String(row.name),
    email: String(row.email ?? ''),
    country: String(row.country ?? ''),
    admin: Number(row.admin),
    active: Number(row.active) === 1,
    privlevel: (row.privlevel as Privilege) || 'NONE',
    registeredAt: numberOrNull(row.registeredAt),
    lastLogin: numberOrNull(row.lastLogin),
    clientVersion: (row.clientVersion as string | null) || null,
  };
}

export type PlayerSearch = {
  players: PlayerSummary[];
  /** How the query was read. */
  by: 'recent' | 'name' | 'email' | 'ip';
};

/**
 * Finds accounts the way a moderator would look for one: by the start of a name
 * or an exact client id, by email, or by an address they connected from. Each
 * goes through one of Servatrice's indexes; nothing here scans the users table.
 */
export async function searchPlayers(query: string, limit = 50): Promise<PlayerSearch> {
  const from = `FROM ${T.users} u LEFT JOIN ${T.analytics} a ON a.id = u.id`;
  if (!query)
    return { by: 'recent', players: (await rows<SummaryRow>(`SELECT ${summaryColumns} ${from} ORDER BY u.id DESC LIMIT ?`, [ limit ])).map(toSummary) };

  if (isIP(query))
    return {
      by: 'ip',
      players: (await rows<SummaryRow>(`
        SELECT ${summaryColumns} ${from}
        WHERE u.name IN (SELECT DISTINCT user_name FROM ${T.sessions} WHERE ip_address = ?)
        ORDER BY a.last_login DESC LIMIT ?`, [ query, limit ])).map(toSummary),
    };

  if (query.includes('@'))
    return {
      by: 'email',
      players: (await rows<SummaryRow>(`SELECT ${summaryColumns} ${from} WHERE u.email = ? ORDER BY u.id LIMIT ?`, [ query.toLowerCase(), limit ])).map(toSummary),
    };

  // A client id is a hash, so an exact match on one is as good as a name.
  const result = await rows<SummaryRow>(`
    SELECT ${summaryColumns} ${from}
    WHERE u.name LIKE ? OR u.clientid = ?
    ORDER BY u.name = ? DESC, u.name LIMIT ?`, [ `${likeEscape(query)}%`, query, query, limit ]);
  return { by: 'name', players: result.map(toSummary) };
}

export type PlayerDetail = PlayerSummary & {
  realname: string;
  clientId: string;
  hasPassword: boolean;
  hasAvatar: boolean;
  /** Null when this schema has no notes column. */
  adminNotes: string | null;
  privlevelStart: number | null;
  privlevelEnd: number | null;
  /** Whether Servatrice will make them pick a new password at their next login (schema 31+). */
  forcePasswordChange: boolean | null;
  /** Their last session's address, the obvious one to ban. */
  lastIp: string | null;
};

export async function getPlayer(name: string): Promise<PlayerDetail | null> {
  const caps = await capabilities();
  const [ row ] = await rows<SummaryRow & Record<string, unknown>>(`
    SELECT ${summaryColumns}, u.realname, u.clientid AS clientId,
      u.password_sha512 <> '' AS hasPassword, LENGTH(u.avatar_bmp) > 0 AS hasAvatar,
      ${caps.adminNotes ? 'u.adminnotes' : 'NULL'} AS adminNotes,
      ${caps.forcePasswordChange ? 'u.force_password_change' : 'NULL'} AS forcePasswordChange,
      ${utcEpoch('u.privlevelStartDate')} AS privlevelStart, ${utcEpoch('u.privlevelEndDate')} AS privlevelEnd,
      (SELECT s.ip_address FROM ${T.sessions} s WHERE s.user_name = u.name ORDER BY s.start_time DESC LIMIT 1) AS lastIp
    FROM ${T.users} u LEFT JOIN ${T.analytics} a ON a.id = u.id
    WHERE u.name = ? LIMIT 1`, [ name ]);
  if (!row) return null;

  return {
    ...toSummary(row),
    realname: String(row.realname ?? ''),
    clientId: String(row.clientId ?? ''),
    hasPassword: Number(row.hasPassword) === 1,
    hasAvatar: Number(row.hasAvatar) === 1,
    adminNotes: caps.adminNotes ? String(row.adminNotes ?? '') : null,
    forcePasswordChange: caps.forcePasswordChange ? Number(row.forcePasswordChange) === 1 : null,
    privlevelStart: numberOrNull(row.privlevelStart),
    privlevelEnd: numberOrNull(row.privlevelEnd),
    lastIp: (row.lastIp as string | null) || null,
  };
}

export type PlayerSession = {
  id: number;
  userName: string;
  ip: string;
  clientId: string;
  connectionType: string | null;
  start: number;
  /** Null while it's open. */
  end: number | null;
};

/** A player's connections, newest first: `getUserSessions`. */
export async function listPlayerSessions(name: string, limit = 50): Promise<PlayerSession[]> {
  const result = await rows<Record<string, unknown>>(`
    SELECT id, user_name AS userName, ip_address AS ip, clientid AS clientId, connection_type AS connectionType,
      UNIX_TIMESTAMP(start_time) AS start, UNIX_TIMESTAMP(end_time) AS end
    FROM ${T.sessions} WHERE user_name = ? ORDER BY start_time DESC LIMIT ?`, [ name, limit ]);
  return result.map((row) => ({
    id: Number(row.id),
    userName: String(row.userName),
    ip: String(row.ip ?? ''),
    clientId: String(row.clientId ?? ''),
    connectionType: (row.connectionType as string | null) ?? null,
    start: Number(row.start),
    end: numberOrNull(row.end),
  }));
}

export type PlayerAlt = PlayerSummary & {
  clientId: string;
  warnings: number;
  bans: number;
  /** What ties it to the player looked up. */
  sharesEmail: boolean;
  sharesClientId: boolean;
};

/**
 * Accounts that look like the same person: the same email or client id, or an
 * address both connected from in the last six months. Servatrice's `getUserAlts`.
 */
export async function findAlts(player: Pick<PlayerDetail, 'name' | 'email' | 'clientId'>): Promise<PlayerAlt[]> {
  const conditions = [ 'u.name IN (SELECT DISTINCT s.user_name FROM ' + T.sessions + ` s
    WHERE s.start_time >= NOW() - INTERVAL 6 MONTH AND s.ip_address IN (
      SELECT DISTINCT s2.ip_address FROM ${T.sessions} s2 WHERE s2.user_name = ? AND s2.start_time >= NOW() - INTERVAL 6 MONTH))` ];
  const params: unknown[] = [ player.name ];
  if (player.email) {
    conditions.push('u.email = ?');
    params.push(player.email);
  }
  if (player.clientId) {
    conditions.push('u.clientid = ?');
    params.push(player.clientId);
  }

  const result = await rows<SummaryRow & Record<string, unknown>>(`
    SELECT ${summaryColumns}, u.clientid AS clientId,
      (SELECT COUNT(*) FROM ${T.warnings} w WHERE w.user_id = u.id) AS warnings,
      (SELECT COUNT(*) FROM ${T.bans} b WHERE b.user_name = u.name) AS bans
    FROM ${T.users} u LEFT JOIN ${T.analytics} a ON a.id = u.id
    WHERE u.name <> ? AND (${conditions.join(' OR ')})
    ORDER BY u.name LIMIT 200`, [ player.name, ...params ]);
  return result.map((row) => ({
    ...toSummary(row),
    clientId: String(row.clientId ?? ''),
    warnings: Number(row.warnings),
    bans: Number(row.bans),
    sharesEmail: Boolean(player.email) && row.email === player.email,
    sharesClientId: Boolean(player.clientId) && row.clientId === player.clientId,
  }));
}

/* ── Bans ─────────────────────────────────────────────────────────────── */

export type Ban = {
  userName: string;
  /** The account the name belongs to; names can be banned without one (a guest's, say). */
  accountId: number | null;
  ip: string;
  clientId: string;
  adminId: number;
  adminName: string | null;
  /** When it was placed, epoch seconds; with `userName`, the row's key. */
  at: number;
  /** 0 is permanent. */
  minutes: number;
  reason: string;
  visibleReason: string;
  /** Null for a permanent ban. */
  expiresAt: number | null;
  /**
   * Whether it stops anyone logging in now. Servatrice only looks at the newest
   * ban for each name, address and client id (`checkUserIsNameBanned` and the
   * rest), so an unexpired ban can still be overridden by a later, shorter one.
   */
  effective: boolean;
};

const unexpired = '(b.minutes = 0 OR b.time_from + INTERVAL b.minutes MINUTE > NOW())';
const newestFor = (column: string) =>
  `(b.${column} <> '' AND b.time_from = (SELECT MAX(c.time_from) FROM ${T.bans} c WHERE c.${column} = b.${column}))`;

const banColumns = `
  b.user_name AS userName, player.id AS accountId, b.ip_address AS ip, b.clientid AS clientId, b.id_admin AS adminId, admin.name AS adminName,
  UNIX_TIMESTAMP(b.time_from) AS at, b.minutes, b.reason, b.visible_reason AS visibleReason,
  IF(b.minutes = 0, NULL, UNIX_TIMESTAMP(b.time_from + INTERVAL b.minutes MINUTE)) AS expiresAt,
  ${unexpired} AND (${newestFor('user_name')} OR ${newestFor('ip_address')} OR ${newestFor('clientid')}) AS effective`;

function toBan(row: Record<string, unknown>): Ban {
  return {
    userName: String(row.userName ?? ''),
    accountId: numberOrNull(row.accountId),
    ip: String(row.ip ?? ''),
    clientId: String(row.clientId ?? ''),
    adminId: Number(row.adminId),
    adminName: (row.adminName as string | null) ?? null,
    at: Number(row.at),
    minutes: Number(row.minutes),
    reason: String(row.reason ?? ''),
    visibleReason: String(row.visibleReason ?? ''),
    expiresAt: numberOrNull(row.expiresAt),
    effective: Number(row.effective) === 1,
  };
}

export async function listBans({ scope = 'active', query = '', limit = 100, offset = 0 }: {
  scope?: 'active' | 'all';
  /** A name (or its start), an address or a client id. */
  query?: string;
  limit?: number;
  offset?: number;
} = {}): Promise<{ bans: Ban[]; total: number }> {
  const where: string[] = [];
  const params: unknown[] = [];
  if (scope === 'active') where.push(unexpired);
  if (query) {
    where.push('(b.user_name LIKE ? OR b.ip_address = ? OR b.clientid = ?)');
    params.push(`${likeEscape(query)}%`, query, query);
  }
  const clause = where.length ? `WHERE ${where.join(' AND ')}` : '';

  const [ result, [ count ] ] = await Promise.all([
    rows<Record<string, unknown>>(`
      SELECT ${banColumns} FROM ${T.bans} b LEFT JOIN ${T.users} admin ON admin.id = b.id_admin LEFT JOIN ${T.users} player ON player.name = b.user_name AND b.user_name <> ''
      ${clause} ORDER BY b.time_from DESC LIMIT ? OFFSET ?`, [ ...params, limit, offset ]),
    rows<{ n: number }>(`SELECT COUNT(*) AS n FROM ${T.bans} b ${clause}`, params),
  ]);
  return { bans: result.map(toBan), total: Number(count?.n ?? 0) };
}

/** Every ban placed on a name, newest first: `getUserBanHistory`. */
export async function banHistory(name: string): Promise<Ban[]> {
  const result = await rows<Record<string, unknown>>(`
    SELECT ${banColumns} FROM ${T.bans} b LEFT JOIN ${T.users} admin ON admin.id = b.id_admin LEFT JOIN ${T.users} player ON player.name = b.user_name AND b.user_name <> ''
    WHERE b.user_name = ? ORDER BY b.time_from DESC`, [ name ]);
  return result.map(toBan);
}

export type NewBan = {
  userName: string;
  ip: string;
  clientId: string;
  /** 0 is permanent. */
  minutes: number;
  /** Only staff see this one. */
  reason: string;
  /** Shown to the player when they're turned away. */
  visibleReason: string;
};

/** Longest a timed ban can be: `minutes` is an int(6). */
export const MAX_BAN_MINUTES = 999_999;

/** Addresses Servatrice won't ban, so neither do we (see `cmdBanFromServer`). */
export const isTrustedSource = (ip: string) => config.cockatrice.trustedSources.some((trusted) => trusted.toLowerCase() === ip.toLowerCase());

/** Places a ban as `cmdBanFromServer` does. Its key is (name, second placed), so two in one second clash. */
export async function addBan(ban: NewBan, adminId: number): Promise<void> {
  await execute(`
    INSERT INTO ${T.bans} (user_name, ip_address, id_admin, time_from, minutes, reason, visible_reason, clientid)
    VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)`,
  [ ban.userName, ban.ip, adminId, ban.minutes, ban.reason, ban.visibleReason, ban.clientId ]);
}

/**
 * Ends a ban early. Servatrice has no unban: its moderators delete the row by
 * hand. Shortening it to the time already served keeps the record instead, and
 * since it stays the newest ban for its name, an older one can't come back.
 * `minutes` can't go below 1 (0 means permanent), so a ban placed under a
 * minute ago still runs out that minute.
 */
export async function liftBan(userName: string, at: number, liftedBy: string): Promise<boolean> {
  const result = await execute(`
    UPDATE ${T.bans} b
    SET b.minutes = GREATEST(1, TIMESTAMPDIFF(MINUTE, b.time_from, NOW())),
        b.reason = CONCAT(b.reason, IF(b.reason = '', '', '\n'), ?)
    WHERE b.user_name = ? AND b.time_from = FROM_UNIXTIME(?) AND ${unexpired}`,
  [ `[Lifted by ${liftedBy} on the website]`, userName, at ]);
  return result.affectedRows > 0;
}

/* ── Warnings ─────────────────────────────────────────────────────────── */

export type Warning = {
  userId: number;
  userName: string;
  modName: string;
  reason: string;
  at: number;
  clientId: string;
};

export async function listWarnings({ userId, limit = 100 }: { userId?: number; limit?: number } = {}): Promise<Warning[]> {
  const result = await rows<Record<string, unknown>>(`
    SELECT user_id AS userId, user_name AS userName, mod_name AS modName, reason, UNIX_TIMESTAMP(time_of) AS at, clientid AS clientId
    FROM ${T.warnings} ${userId === undefined ? '' : 'WHERE user_id = ?'}
    ORDER BY time_of DESC LIMIT ?`, userId === undefined ? [ limit ] : [ userId, limit ]);
  return result.map((row) => ({
    userId: Number(row.userId),
    userName: String(row.userName),
    modName: String(row.modName),
    reason: String(row.reason ?? ''),
    at: Number(row.at),
    clientId: String(row.clientId ?? ''),
  }));
}

/** Records a warning as `addWarning` does. Keyed on (user, second), like bans. */
export async function addWarning(player: Pick<PlayerDetail, 'id' | 'name' | 'clientId'>, modName: string, reason: string): Promise<void> {
  await execute(`
    INSERT INTO ${T.warnings} (user_id, user_name, mod_name, reason, time_of, clientid) VALUES (?, ?, ?, ?, NOW(), ?)`,
  [ player.id, player.name, modName, reason, player.clientId ]);
}

/* ── Account changes ──────────────────────────────────────────────────── */

/** As a moderator's force-activate does; also drops a pending activation email, which would only confuse them now. */
export async function activateAccount(player: Pick<PlayerDetail, 'id' | 'name'>): Promise<void> {
  await execute(`UPDATE ${T.users} SET active = 1 WHERE id = ?`, [ player.id ]);
  await execute(`DELETE FROM ${T.activation} WHERE name = ?`, [ player.name ]);
}

export async function setAdminNotes(accountId: number, notes: string): Promise<void> {
  if (!(await capabilities()).adminNotes) throw new Error('this schema has no adminnotes column');
  await execute(`UPDATE ${T.users} SET adminnotes = ? WHERE id = ?`, [ notes, accountId ]);
}

/** Sets the moderator, judge and developer bits, as `AdjustMod` does. The admin bit is left alone. */
export async function setStaffFlags(accountId: number, flags: number): Promise<void> {
  await execute(`UPDATE ${T.users} SET admin = (admin & ~?) | ? WHERE id = ?`,
    [ ADJUSTABLE_FLAGS, flags & ADJUSTABLE_FLAGS, accountId ]);
}

/**
 * Servatrice only shows the privilege level (and lets it into rooms that ask
 * for one); it never looks at the dates, so they're for staff's own records.
 */
export async function setPrivilege(accountId: number, level: Privilege, endsAt: Date | null): Promise<void> {
  await execute(`
    UPDATE ${T.users} SET privlevel = ?,
      privlevelStartDate = IF(privlevel = ?, privlevelStartDate, UTC_TIMESTAMP()),
      privlevelEndDate = COALESCE(?, UTC_TIMESTAMP())
    WHERE id = ?`, [ level, level, endsAt, accountId ]);
}

/**
 * Where an account is known by name rather than id ([table, column], ours
 * unprefixed): what follows the account to its new name. Bans have to, or a
 * rename would lift them; warnings, sessions and reports keep its history
 * findable. Games and the chat log stay as they happened, under the old name,
 * as does the donations table. `activation_emails` usually cascades from
 * `users.name`, but not every database has the key.
 */
const NAME_COLUMNS: [string, string][] = [
  [ 'sessions', 'user_name' ],
  [ 'bans', 'user_name' ],
  [ 'warnings', 'user_name' ],
  [ 'warnings', 'mod_name' ],
  [ 'reports', 'reporter_name' ],
  [ 'reports', 'reported_user_name' ],
  [ 'report_comments', 'author_name' ],
  [ 'activation_emails', 'name' ],
  [ 'forgot_password', 'name' ],
  [ 'audit', 'name' ],
];
const OUR_NAME_COLUMNS: [string, string][] = [
  [ 'chickatrice_admin_log', 'target' ],
  [ 'chickatrice_admin_log', 'actor_name' ],
];

/**
 * Why `name` can't be taken by account `accountId`, or null if it can. The
 * collation is case-insensitive, so changing only the case of a name is fine.
 */
export async function renameProblem(accountId: number, name: string): Promise<string | null> {
  const [ taken ] = await rows(`SELECT 1 FROM ${T.users} WHERE name = ? AND id <> ? LIMIT 1`, [ name, accountId ]);
  if (taken) return `${name} is taken.`;
  // Bans on a name outlive the account that had it; taking the name would inherit them.
  const [ banned ] = await rows(`
    SELECT 1 FROM ${T.bans} b JOIN ${T.users} u ON u.id = ?
    WHERE b.user_name = ? AND b.user_name <> u.name LIMIT 1`, [ accountId, name ]);
  if (banned) return `${name} has bans of its own.`;
  return null;
}

/**
 * Servatrice has no rename command; this changes `users.name` and every row
 * that refers to the account by name, in one transaction, and adds the change
 * to the account's name history. Callers check {@link renameProblem} first.
 */
export async function renamePlayer(
  accountId: number,
  oldName: string,
  newName: string,
  change: { source: 'user' } | { source: 'staff'; by: number },
): Promise<void> {
  const prefixed = NAME_COLUMNS.map(([ name, column ]): [string, string] => [ `${config.cockatrice.prefix}_${name}`, column ]);
  const targets = [ ...prefixed, ...OUR_NAME_COLUMNS ];

  // Older schemas lack some of these tables (reports, for one).
  const [ present ] = await pool.query(
    `SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME) AS name FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?)`,
    [ [ ...new Set(targets.map(([ name ]) => name)) ] ]);
  const columns = new Set((present as { name: string }[]).map((row) => row.name));

  await db.transaction(async (tx) => {
    await tx.execute(sql`UPDATE ${sql.identifier(`${config.cockatrice.prefix}_users`)} SET name = ${newName} WHERE id = ${accountId}`);
    for (const [ name, column ] of targets) {
      if (!columns.has(`${name}.${column}`)) continue;
      await tx.execute(sql`UPDATE ${sql.identifier(name)} SET ${sql.identifier(column)} = ${newName} WHERE ${sql.identifier(column)} = ${oldName}`);
    }
    await tx.insert(chickatrice.nameChanges).values({
      accountId,
      oldName,
      newName,
      source: change.source,
      changedBy: change.source === 'staff' ? change.by : null,
      createdAt: sql`UTC_TIMESTAMP()`,
    });
  });
}

const PASSWORD_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

/**
 * `ResetUserPassword`: a random password, which Servatrice (schema 31+) makes
 * them change the first time they use it. Returns it to hand to the player.
 */
export async function resetPassword(accountId: number): Promise<string> {
  const length = Math.max(12, config.cockatrice.minPasswordLength);
  const password = Array.from({ length }, () => PASSWORD_ALPHABET[randomInt(PASSWORD_ALPHABET.length)]).join('');
  await setPassword(accountId, password);
  // setPassword clears the flag, as a password the owner chose should.
  if ((await capabilities()).forcePasswordChange)
    await execute(`UPDATE ${T.users} SET force_password_change = 1 WHERE id = ?`, [ accountId ]);
  return password;
}

/** Everyone with a staff flag, and when they last logged in: `getModeratorLastLogins`. */
export async function listStaff(): Promise<PlayerSummary[]> {
  const result = await rows<SummaryRow>(`
    SELECT ${summaryColumns} FROM ${T.users} u LEFT JOIN ${T.analytics} a ON a.id = u.id
    WHERE (u.admin & 15) <> 0 ORDER BY u.admin & 1 DESC, u.admin & 2 DESC, u.name`);
  return result.map(toSummary);
}

/** Bans placed (by account id) and warnings given (by name) in the last `days` days, from the client or the site. */
export async function staffActivity(days = 30): Promise<{ bans: Map<number, number>; warnings: Map<string, number> }> {
  const [ bans, warnings ] = await Promise.all([
    rows<{ id: number; n: number }>(`SELECT id_admin AS id, COUNT(*) AS n FROM ${T.bans} WHERE time_from >= NOW() - INTERVAL ? DAY GROUP BY id_admin`, [ days ]),
    rows<{ name: string; n: number }>(`SELECT mod_name AS name, COUNT(*) AS n FROM ${T.warnings} WHERE time_of >= NOW() - INTERVAL ? DAY GROUP BY mod_name`, [ days ]),
  ]);
  return {
    bans: new Map(bans.map((row) => [ Number(row.id), Number(row.n) ])),
    warnings: new Map(warnings.map((row) => [ row.name.toLowerCase(), Number(row.n) ])),
  };
}
