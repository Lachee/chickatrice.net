import { error, redirect, type RequestEvent } from '@sveltejs/kit';
import type { Account } from '#lib/server/cockatrice/accounts.ts';
import { staffAccess, type StaffAccess } from '#lib/staff.ts';

/** The signed-in account, or a redirect to the login page that comes back here afterwards. */
export function requireAccount(event: Pick<RequestEvent, 'locals' | 'url'>): Account {
  const { account } = event.locals;
  if (!account)
    redirect(303, `/login?next=${encodeURIComponent(event.url.pathname)}`);
  return account;
}

/**
 * The signed-in staff account, if it may do `need`. `admin` is read afresh on
 * every request (hooks.server.ts), so a demotion takes effect on the next one.
 * Form actions don't run layout loads: each one has to call this itself.
 */
export function requireStaff(event: Pick<RequestEvent, 'locals' | 'url'>, need: keyof StaffAccess): Account & { access: StaffAccess } {
  const account = requireAccount(event);
  const access = staffAccess(account.admin);
  if (!access[need])
    error(403, need === 'view' ? 'This is for server staff.' : "Your staff role doesn't allow that.");
  return { ...account, access };
}

/** Only same-site paths, so `?next=` can't bounce someone off to another site. */
export function safeNext(value: string | null, fallback = '/account'): string {
  if (!value?.startsWith('/'))
    return fallback;
  // Parsed the way a browser would: `//x`, `/\\x` and `/<tab>/x` all leave the site.
  const base = 'http://localhost';
  try {
    const url = new URL(value, base);
    return url.origin === base ? url.pathname + url.search + url.hash : fallback;
  } catch {
    return fallback;
  }
}
