import { config } from '#lib/server/config.ts';
import { pool } from '#lib/server/db/index.ts';
import { capabilities } from './capabilities';
import { execute, numberOrNull, rows, table } from './sql';

/*
 * The server settings Servatrice keeps in its database rather than in
 * servatrice.ini: rooms and their game types (used with `[rooms] method=sql`),
 * the message players see on logging in, and the card art rules.
 *
 * Servatrice reads rooms once, at startup, and the server message at startup or
 * when an admin sends "Update server message" from the client. Changes here
 * wait for one of those. Card art rules are read on each use.
 */

const T = {
  rooms: table('rooms'),
  gameTypes: table('rooms_gametypes'),
  messages: table('servermessages'),
  cardArt: table('card_art_name_rules'),
  users: table('users'),
};

const serverId = () => config.cockatrice.serverId;

/** `permissionlevel`: who may join. */
export const ROOM_PERMISSIONS = [ 'NONE', 'REGISTERED', 'MODERATOR', 'ADMINISTRATOR' ] as const;
/** `privlevel`: which privilege level (on top of that) may join. */
export const ROOM_PRIVILEGES = [ 'NONE', 'PRIVILEGED', 'VIP', 'DONATOR' ] as const;

export type Room = {
  id: number;
  name: string;
  description: string;
  permissionLevel: (typeof ROOM_PERMISSIONS)[number];
  privilegeLevel: (typeof ROOM_PRIVILEGES)[number];
  autoJoin: boolean;
  joinMessage: string;
  chatHistorySize: number;
  gameTypes: string[];
};

export type RoomInput = Omit<Room, 'id'>;

/** Column sizes in servatrice.sql. */
export const ROOM_LIMITS = { name: 50, description: 255, joinMessage: 255, gameType: 50, chatHistorySize: 9999 };

export async function listRooms(): Promise<Room[]> {
  const [ rooms, types ] = await Promise.all([
    rows<Record<string, unknown>>(`
      SELECT id, name, descr, permissionlevel, privlevel, auto_join, join_message, chat_history_size
      FROM ${T.rooms} WHERE id_server = ? ORDER BY id`, [ serverId() ]),
    // No order column: Servatrice reads them back in the order they went in, so that's the order kept.
    rows<{ room: number; name: string }>(`SELECT id_room AS room, name FROM ${T.gameTypes} WHERE id_server = ?`, [ serverId() ]),
  ]);
  return rooms.map((row) => ({
    id: Number(row.id),
    name: String(row.name),
    description: String(row.descr ?? ''),
    permissionLevel: String(row.permissionlevel).toUpperCase() as Room['permissionLevel'],
    privilegeLevel: String(row.privlevel).toUpperCase() as Room['privilegeLevel'],
    autoJoin: Number(row.auto_join) === 1,
    joinMessage: String(row.join_message ?? ''),
    chatHistorySize: Number(row.chat_history_size),
    gameTypes: types.filter((type) => Number(type.room) === Number(row.id)).map((type) => type.name),
  }));
}

/** Writes a room and replaces its game types, together. Returns its id. */
export async function saveRoom(id: number | null, room: RoomInput): Promise<number> {
  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();
    const values = [ room.name, room.description, room.permissionLevel, room.privilegeLevel, room.autoJoin ? 1 : 0, room.joinMessage, room.chatHistorySize ];
    let roomId = id;
    if (roomId === null) {
      const [ result ] = await connection.query(`
        INSERT INTO ${T.rooms} (name, descr, permissionlevel, privlevel, auto_join, join_message, chat_history_size, id_server)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)`, [ ...values, serverId() ]);
      roomId = (result as { insertId: number }).insertId;
    } else {
      const [ result ] = await connection.query(`
        UPDATE ${T.rooms} SET name = ?, descr = ?, permissionlevel = ?, privlevel = ?, auto_join = ?, join_message = ?, chat_history_size = ?
        WHERE id = ? AND id_server = ?`, [ ...values, roomId, serverId() ]);
      if (!(result as { affectedRows: number }).affectedRows) throw new Error(`room ${roomId} not found`);
    }

    await connection.query(`DELETE FROM ${T.gameTypes} WHERE id_room = ? AND id_server = ?`, [ roomId, serverId() ]);
    if (room.gameTypes.length)
      await connection.query(`INSERT INTO ${T.gameTypes} (id_room, name, id_server) VALUES ?`,
        [ room.gameTypes.map((name) => [ roomId, name, serverId() ]) ]);

    await connection.commit();
    return roomId;
  } catch (e) {
    await connection.rollback();
    throw e;
  } finally {
    connection.release();
  }
}

export async function deleteRoom(id: number): Promise<boolean> {
  // Game types would cascade, but not every database has the key.
  await execute(`DELETE FROM ${T.gameTypes} WHERE id_room = ? AND id_server = ?`, [ id, serverId() ]);
  const result = await execute(`DELETE FROM ${T.rooms} WHERE id = ? AND id_server = ?`, [ id, serverId() ]);
  return result.affectedRows > 0;
}

export type ServerMessage = { at: number; message: string };

/** Newest first; Servatrice shows the newest. */
export async function listServerMessages(limit = 10): Promise<ServerMessage[]> {
  const result = await rows<{ at: number; message: string | null }>(`
    SELECT UNIX_TIMESTAMP(timest) AS at, message FROM ${T.messages} WHERE id_server = ? ORDER BY timest DESC LIMIT ?`,
  [ serverId(), limit ]);
  return result.map((row) => ({ at: Number(row.at), message: row.message ?? '' }));
}

/** Keyed on the second it was posted, so two in one second clash. */
export async function postServerMessage(message: string): Promise<void> {
  await execute(`INSERT INTO ${T.messages} (id_server, timest, message) VALUES (?, NOW(), ?)`, [ serverId(), message ]);
}

export type CardArtRule = {
  id: number;
  cardName: string;
  providerId: string;
  mode: 'ALLOW' | 'DENY';
  reason: string | null;
  createdBy: string | null;
  createdAt: number | null;
};

/** `MAX_NAME_LENGTH` in serversocketinterface.cpp is far longer than the column; the column wins. */
export const CARD_ART_LIMIT = 255;

export async function listCardArtRules(): Promise<CardArtRule[]> {
  if (!(await capabilities()).cardArtRules) return [];
  const result = await rows<Record<string, unknown>>(`
    SELECT r.id, r.card_name AS cardName, r.card_provider_id AS providerId, r.mode, r.reason, u.name AS createdBy,
      UNIX_TIMESTAMP(r.created_at) AS createdAt
    FROM ${T.cardArt} r LEFT JOIN ${T.users} u ON u.id = r.created_by
    ORDER BY r.card_name, r.card_provider_id`);
  return result.map((row) => ({
    id: Number(row.id),
    cardName: String(row.cardName),
    providerId: String(row.providerId ?? ''),
    mode: row.mode as CardArtRule['mode'],
    reason: (row.reason as string | null) || null,
    createdBy: (row.createdBy as string | null) ?? null,
    createdAt: numberOrNull(row.createdAt),
  }));
}

/** As `cmdAddCardArtRule`: one rule per card and printing, replaced if it exists. */
export async function setCardArtRule(rule: { cardName: string; providerId: string; mode: 'ALLOW' | 'DENY'; reason: string }, staffId: number) {
  await execute(`
    INSERT INTO ${T.cardArt} (card_name, card_provider_id, mode, reason, created_by) VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE mode = VALUES(mode), reason = VALUES(reason)`,
  [ rule.cardName, rule.providerId, rule.mode, rule.reason || null, staffId ]);
}

/** Returns what was removed, for the log. */
export async function removeCardArtRule(id: number): Promise<{ cardName: string; providerId: string } | null> {
  const [ rule ] = await rows<{ cardName: string; providerId: string }>(
    `SELECT card_name AS cardName, card_provider_id AS providerId FROM ${T.cardArt} WHERE id = ?`, [ id ]);
  if (rule) await execute(`DELETE FROM ${T.cardArt} WHERE id = ?`, [ id ]);
  return rule ?? null;
}
