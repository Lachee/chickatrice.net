import { eq, sql } from 'drizzle-orm';
import { db } from '#lib/server/db/index.ts';
import { siteBanner as banner } from '#lib/server/db/chickatrice.schema.ts';

/*
 * The site banner: one row in `chickatrice_site_banner`, read on every page load,
 * so it's cached here. Other replicas notice a change within `CACHE_SECONDS`.
 */

export const BANNER_LEVELS = [ 'info', 'warning', 'outage' ] as const;
export type BannerLevel = (typeof BANNER_LEVELS)[number];
export const BANNER_MAX_LENGTH = 500;

export type Banner = {
  message: string;
  level: BannerLevel;
  /** Epoch seconds, or null for no expiry. */
  expiresAt: number | null;
  /** Epoch seconds; with the message, tells browsers whether they dismissed this one. */
  updatedAt: number;
};

const ID = 1;
const CACHE_SECONDS = 30;
let cache: { at: number; value: Promise<Banner | null> } | null = null;

const epoch = (date: Date) => Math.floor(date.getTime() / 1000);

/** The banner as stored, expired or not (for the admin form). */
export async function getStoredBanner(): Promise<Banner | null> {
  const [ row ] = await db.select().from(banner).where(eq(banner.id, ID));
  if (!row) return null;
  return {
    message: row.message,
    level: row.level,
    expiresAt: row.expiresAt && epoch(row.expiresAt),
    updatedAt: epoch(row.updatedAt),
  };
}

/** The banner to show now, or null. Never throws: a page shouldn't fail over its banner. */
export async function getActiveBanner(): Promise<Banner | null> {
  if (!cache || cache.at + CACHE_SECONDS * 1000 < Date.now()) {
    const value = getStoredBanner().catch((e) => {
      console.error('[banner] could not read the banner', e);
      cache = null;
      return null;
    });
    cache = { at: Date.now(), value };
  }
  const current = await cache.value;
  if (current?.expiresAt && current.expiresAt * 1000 <= Date.now()) return null;
  return current;
}

export async function setBanner(input: { message: string; level: BannerLevel; expiresAt: Date | null }, staffId: number) {
  const values = { ...input, updatedBy: staffId, updatedAt: sql`UTC_TIMESTAMP()` };
  await db.insert(banner).values({ id: ID, ...values }).onDuplicateKeyUpdate({ set: values });
  cache = null;
}

export async function clearBanner(): Promise<boolean> {
  const [ result ] = await db.delete(banner).where(eq(banner.id, ID));
  cache = null;
  return result.affectedRows > 0;
}
