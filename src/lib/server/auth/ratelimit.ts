/**
 * A fixed-window attempt counter, held in memory. Enough to make guessing an
 * in-game password over the web slow; it is per process, so a deployment with
 * several replicas allows each its own budget.
 */
export class RateLimiter {
  private readonly hits = new Map<string, { count: number; resetAt: number }>();

  constructor(private readonly limit: number, private readonly windowMs: number) {}

  /** Records an attempt under `key`. Returns the seconds to wait if it's one too many, else 0. */
  hit(key: string): number {
    const now = Date.now();
    this.sweep(now);

    const entry = this.hits.get(key);
    if (!entry || entry.resetAt <= now) {
      this.hits.set(key, { count: 1, resetAt: now + this.windowMs });
      return 0;
    }

    entry.count++;
    return entry.count > this.limit ? Math.ceil((entry.resetAt - now) / 1000) : 0;
  }

  /** Forgets `key`, e.g. after a successful sign-in. */
  reset(key: string) {
    this.hits.delete(key);
  }

  private lastSweep = 0;
  private sweep(now: number) {
    if (now - this.lastSweep < this.windowMs) return;
    this.lastSweep = now;
    for (const [ key, entry ] of this.hits)
      if (entry.resetAt <= now) this.hits.delete(key);
  }
}

/**
 * The key an address is limited under. IPv6 addresses are taken by their /64:
 * that is what one home or server gets, so anyone could rotate through a
 * fresh address per attempt otherwise.
 */
export function addressKey(address: string): string {
  const ip = address.split('%')[0]; // zone index, on link-local addresses
  if (!ip.includes(':')) return ip;
  const mapped = ip.match(/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i);
  if (mapped) return mapped[1];

  const [ head, tail ] = ip.split('::');
  const left = head ? head.split(':') : [];
  const right = tail ? tail.split(':') : [];
  const groups = tail === undefined ? left : [ ...left, ...Array(8 - left.length - right.length).fill('0'), ...right ];
  return groups.slice(0, 4).map((group) => parseInt(group, 16).toString(16)).join(':') + '::/64';
}
