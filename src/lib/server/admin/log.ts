import { desc, eq, sql } from 'drizzle-orm';
import { db, chickatrice } from '#lib/server/db/index.ts';

const { adminLog } = chickatrice;

export type AdminAction =
  | 'ban' | 'ban.lift' | 'warn'
  | 'account.activate' | 'account.password' | 'account.avatar' | 'account.notes' | 'account.signout'
  | 'account.staff' | 'account.privilege'
  | 'report.assign' | 'report.resolve' | 'report.dismiss' | 'report.comment'
  | 'room.create' | 'room.update' | 'room.delete'
  | 'servermessage'
  | 'cardart.set' | 'cardart.remove';

export type AdminLogEntry = {
  id: number;
  actorName: string;
  action: string;
  target: string | null;
  details: string | null;
  /** Epoch seconds. */
  at: number;
};

/** Records something staff did. Never throws: the action already happened, and a lost log line shouldn't undo it. */
export async function logAdminAction(
  actor: { id: number; name: string },
  action: AdminAction,
  target: string | null,
  details?: string | null,
): Promise<void> {
  try {
    await db.insert(adminLog).values({
      actorId: actor.id,
      actorName: actor.name,
      action,
      target: target?.slice(0, 255) ?? null,
      details: details?.slice(0, 65535) ?? null,
      createdAt: sql`UTC_TIMESTAMP()`,
    });
  } catch (e) {
    console.error(`[admin] could not log ${action} by ${actor.name}`, e);
  }
  console.log(`[admin] ${actor.name} ${action}${target ? ` ${target}` : ''}`);
}

export async function listAdminLog({ target, limit = 100 }: { target?: string; limit?: number } = {}): Promise<AdminLogEntry[]> {
  const rows = await db.select().from(adminLog)
    .where(target ? eq(adminLog.target, target) : undefined)
    .orderBy(desc(adminLog.id))
    .limit(limit);
  return rows.map((row) => ({
    id: row.id,
    actorName: row.actorName,
    action: row.action,
    target: row.target,
    details: row.details,
    at: Math.floor(row.createdAt.getTime() / 1000),
  }));
}

/** How many things each staff account has done from the site in the last `days` days. */
export async function countAdminActions(days = 30): Promise<Map<number, number>> {
  const result = await db.select({ actorId: adminLog.actorId, n: sql<number>`COUNT(*)` }).from(adminLog)
    .where(sql`${adminLog.createdAt} >= UTC_TIMESTAMP() - INTERVAL ${days} DAY`)
    .groupBy(adminLog.actorId);
  return new Map(result.map((row) => [ row.actorId, Number(row.n) ]));
}
