import type { Cookies } from '@sveltejs/kit';
import type { Flash } from '#lib/flash.js';

const COOKIE = 'chickatrice_flash';

export function setFlash(cookies: Cookies, flash: Flash) {
  cookies.set(COOKIE, encodeURIComponent(JSON.stringify(flash)), {
    path: '/',
    httpOnly: true,
    sameSite: 'lax',
    maxAge: 60,
  });
}

/** Reads the pending message, if any, and clears it. */
export function takeFlash(cookies: Cookies): Flash | null {
  const value = cookies.get(COOKIE);
  if (!value) return null;
  cookies.delete(COOKIE, { path: '/' });

  try {
    const parsed = JSON.parse(decodeURIComponent(value));
    if ((parsed.type === 'success' || parsed.type === 'error' || parsed.type === 'info') && typeof parsed.message === 'string')
      return parsed as Flash;
  } catch { /* someone else's cookie, or a mangled one */ }
  return null;
}
