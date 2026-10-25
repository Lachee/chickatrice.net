import { redirect, type RequestEvent } from '@sveltejs/kit';
import type { Account } from '#lib/server/cockatrice/accounts.ts';

/** The signed-in account, or a redirect to the login page that comes back here afterwards. */
export function requireAccount(event: Pick<RequestEvent, 'locals' | 'url'>): Account {
  const { account } = event.locals;
  if (!account)
    redirect(303, `/login?next=${encodeURIComponent(event.url.pathname)}`);
  return account;
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
