import { fail, type RequestEvent } from '@sveltejs/kit';
import { isIP } from 'node:net';
import { requireStaff } from '#lib/server/auth/guard.ts';
import { logAdminAction } from '#lib/server/admin/log.ts';
import { integer, text } from '#lib/server/admin/form.ts';
import { getOnlinePlayers } from '#lib/server/cockatrice/metrics.ts';
import { isDuplicate } from '#lib/server/cockatrice/sql.ts';
import {
  MAX_BAN_MINUTES,
  addBan,
  getPlayer,
  isTrustedSource,
  liftBan,
  type PlayerDetail,
} from '#lib/server/cockatrice/moderation.ts';
import type { Account } from '#lib/server/cockatrice/accounts.ts';
import type { StaffAccess } from '#lib/staff.ts';

/*
 * Form actions more than one admin page offers. Each returns what SvelteKit's
 * actions return: `{ action, message }`, or a `fail` with `{ action, error }`.
 */

type Staff = Account & { access: StaffAccess };

/**
 * Why `staff` may not moderate `target`, or null if they may. Servatrice only
 * guards password resets this way (`cmdResetUserPassword`); here it covers
 * everything done to an account, and admins are left to the database.
 */
export function protectedFrom(staff: Staff, target: Pick<PlayerDetail, 'id' | 'admin'>): string | null {
  if (staff.id === target.id) return "You can't do that to your own account.";
  if (target.admin & 1) return "Admins can't be moderated from here; change them in the database.";
  if (target.admin & 2 && !staff.access.administer) return 'Only an admin can do that to a moderator.';
  return null;
}

/** `` "1 day" ``, `` "permanently" ``: how long a ban is, for messages and the log. */
export function banLength(minutes: number): string {
  if (minutes === 0) return 'permanently';
  const units: [number, string][] = [ [ 43200, 'month' ], [ 10080, 'week' ], [ 1440, 'day' ], [ 60, 'hour' ], [ 1, 'minute' ] ];
  const [ size, unit ] = units.find(([ size ]) => minutes % size === 0) ?? [ 1, 'minute' ];
  const n = minutes / size;
  return `for ${n} ${unit}${n === 1 ? '' : 's'}`;
}

export async function placeBan(event: RequestEvent) {
  const staff = requireStaff(event, 'moderate');
  const form = await event.request.formData();
  const userName = text(form, 'userName');
  const ip = text(form, 'ip');
  const clientId = text(form, 'clientId');
  const reason = text(form, 'reason');
  const visibleReason = text(form, 'visibleReason');
  const preset = text(form, 'minutes');
  const minutes = preset === 'custom' ? integer(form, 'customMinutes') : integer(form, 'minutes');
  const error = (message: string) => fail(400, { action: 'ban', error: message });

  if (!userName && !ip && !clientId) return error('Ban a name, an address or a client id, at least.');
  if (userName.length > 35) return error("That name is longer than any Cockatrice name can be.");
  if (ip && !isIP(ip)) return error(`${ip} isn't an IP address.`);
  if (ip && isTrustedSource(ip)) return error(`${ip} is a trusted source, which Servatrice never bans: every player behind it would be.`);
  if (clientId.length > 15) return error("That client id is too long; they're 15 characters.");
  if (minutes === null || minutes < 0 || minutes > MAX_BAN_MINUTES) return error('Choose how long the ban lasts.');
  if (!reason) return error('Give a reason, for the other staff.');

  let target: PlayerDetail | null = null;
  if (userName) {
    target = await getPlayer(userName);
    const refusal = target && protectedFrom(staff, target);
    if (refusal) return error(refusal);
  }

  try {
    await addBan({ userName: target?.name ?? userName, ip, clientId, minutes, reason, visibleReason }, staff.id);
  } catch (e) {
    if (isDuplicate(e)) return error('That ban was already placed a moment ago.');
    throw e;
  }

  const who = target?.name ?? (userName || ip || clientId);
  const keys = [ userName && 'name', ip && `address ${ip}`, clientId && `client id ${clientId}` ].filter(Boolean).join(', ');
  // Targets without a prefix are names; see listAdminLog's readers.
  await logAdminAction(staff, 'ban', target?.name ?? (userName || (ip ? `ip:${ip}` : `client:${clientId}`)), `${banLength(minutes)} by ${keys}: ${reason}`);

  // Servatrice only checks bans when someone logs in, and nothing tells it about this one.
  const online = await getOnlinePlayers().catch(() => null);
  const connected = online?.players.some((p) => p.name === who || (ip && p.ip === ip) || (clientId && p.clientId === clientId));
  return {
    action: 'ban',
    message: `Banned ${who} ${banLength(minutes)}.${connected ? ' They are connected right now and stay connected until they log out: to remove them now, ban them from the client too.' : ''}`,
  };
}

export async function liftBanAction(event: RequestEvent) {
  const staff = requireStaff(event, 'moderate');
  const form = await event.request.formData();
  const userName = String(form.get('userName') ?? ''); // may be '' for an address ban, and isn't trimmed: it's a key
  const at = integer(form, 'at');
  if (at === null) return fail(400, { action: 'liftBan', error: 'Which ban?' });

  if (!(await liftBan(userName, at, staff.name)))
    return fail(400, { action: 'liftBan', error: 'That ban has already ended.' });
  await logAdminAction(staff, 'ban.lift', userName || null, `ban placed at ${new Date(at * 1000).toISOString()}`);
  return { action: 'liftBan', message: `Lifted the ban${userName ? ` on ${userName}` : ''}.` };
}
