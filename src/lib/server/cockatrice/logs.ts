import { likeEscape } from '#lib/server/admin/form.ts';
import { numberOrNull, rows, table } from './sql';

/*
 * What Servatrice records as it goes: chat, when servatrice.ini's [logging]
 * turns it on, and the audit trail of registrations, password resets and
 * activations, when [audit] does.
 */

const T = {
  log: table('log'),
  audit: table('audit'),
};

export type ChatTarget = 'room' | 'game' | 'chat';

export type ChatMessage = {
  at: number;
  senderId: number | null;
  senderName: string;
  /** Empty when the viewer may not see addresses. */
  senderIp: string;
  message: string;
  targetType: ChatTarget;
  targetId: number | null;
  /** The room, the game's description, or who a private message went to. */
  targetName: string;
};

export type ChatSearch = {
  /** Sent by, or privately to, this player. */
  user?: string;
  ip?: string;
  gameId?: number;
  /** A room's name, or a game's description. */
  target?: string;
  /** Anywhere in the message. */
  text?: string;
  types: ChatTarget[];
  /** Only the last this many hours. 0 is no limit. */
  hours: number;
  limit: number;
  /**
   * Developers see the log without private messages or addresses, as their
   * own command to read it gives them (`processExtendedDeveloperCommand`).
   */
  restricted: boolean;
};

/**
 * Servatrice's `getMessageLogHistory`, newest first. With nothing to narrow it
 * by, only the newest messages are listed, which the log_time index makes cheap.
 */
export async function searchChatLog(search: ChatSearch): Promise<ChatMessage[]> {
  const where: string[] = [];
  const params: unknown[] = [];
  if (search.user) {
    where.push('(sender_name = ? OR target_name = ?)');
    params.push(search.user, search.user);
  }
  if (search.ip && !search.restricted) {
    where.push('sender_ip = ?');
    params.push(search.ip);
  }
  if (search.gameId) {
    where.push(`target_type = 'game' AND target_id = ?`);
    params.push(search.gameId);
  }
  if (search.target) {
    where.push('target_name = ?');
    params.push(search.target);
  }
  if (search.text) {
    where.push('log_message LIKE ?');
    params.push(`%${likeEscape(search.text)}%`);
  }
  const types = search.restricted ? search.types.filter((type) => type !== 'chat') : search.types;
  where.push(types.length ? 'target_type IN (?)' : 'FALSE');
  if (types.length) params.push(types);
  if (search.hours > 0) {
    where.push('log_time >= NOW() - INTERVAL ? HOUR');
    params.push(search.hours);
  }

  const result = await rows<Record<string, unknown>>(`
    SELECT UNIX_TIMESTAMP(log_time) AS at, sender_id AS senderId, sender_name AS senderName, sender_ip AS senderIp,
      log_message AS message, target_type AS targetType, target_id AS targetId, target_name AS targetName
    FROM ${T.log} WHERE ${where.join(' AND ')}
    ORDER BY log_time DESC LIMIT ?`, [ ...params, search.limit ]);
  return result.map((row) => ({
    at: Number(row.at),
    senderId: numberOrNull(row.senderId),
    senderName: String(row.senderName),
    senderIp: search.restricted ? '' : String(row.senderIp ?? ''),
    message: String(row.message ?? ''),
    targetType: row.targetType as ChatTarget,
    targetId: numberOrNull(row.targetId),
    targetName: String(row.targetName ?? ''),
  }));
}

export type AuditEntry = {
  id: number;
  name: string;
  ip: string;
  clientId: string;
  at: number;
  action: string;
  success: boolean;
  details: string;
};

export async function listAudit({ name, action, limit = 100 }: { name?: string; action?: string; limit?: number }): Promise<AuditEntry[]> {
  const where: string[] = [];
  const params: unknown[] = [];
  if (name) {
    where.push('name = ?');
    params.push(name);
  }
  if (action) {
    where.push('action = ?');
    params.push(action);
  }
  const result = await rows<Record<string, unknown>>(`
    SELECT id, name, ip_address AS ip, clientid AS clientId, UNIX_TIMESTAMP(incidentDate) AS at, action, results, details
    FROM ${T.audit} ${where.length ? `WHERE ${where.join(' AND ')}` : ''}
    ORDER BY id DESC LIMIT ?`, [ ...params, limit ]);
  return result.map((row) => ({
    id: Number(row.id),
    name: String(row.name),
    ip: String(row.ip ?? ''),
    clientId: String(row.clientId ?? ''),
    at: Number(row.at),
    action: String(row.action),
    success: row.results === 'success',
    details: String(row.details ?? ''),
  }));
}

/** The audit actions Servatrice writes, for the filter. */
export const AUDIT_ACTIONS = [
  'REGISTER_ACCOUNT', 'ACTIVATE_ACCOUNT', 'PASSWORD_RESET_REQUEST', 'PASSWORD_RESET_CHALLENGE', 'PASSWORD_RESET',
  'REPORT_RESOLVED', 'REPORT_DISMISSED', 'REMOVE_USER_AVATAR',
];
