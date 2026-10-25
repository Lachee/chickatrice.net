import { inArray, sql } from 'drizzle-orm';
import { db, chickatrice } from '#lib/server/db/index.ts';

/*
 * Card images from Scryfall (https://scryfall.com/docs/api). Its API is asked
 * for each card's image URLs, 75 cards a request through /cards/collection,
 * and the answers are kept in `chickatrice_card_images`. Browsers then load
 * the images from Scryfall's CDN, which it's happy to have linked to.
 *
 * Cards are looked up by printing when the deck says which (Cockatrice's
 * provider id is the Scryfall id), else by name and set, else by name alone.
 * Two-faced and split cards are looked up by their first face, which is the
 * only name Scryfall's collection endpoint finds both kinds by.
 */

const { cardImages: cache } = chickatrice;

export type CardRef = { name: string; set?: string; providerId?: string };

export type CardImage = {
  normal: string;
  artCrop: string | null;
  artist: string | null;
  /** The card's page on Scryfall. */
  uri: string | null;
};

const FOUND_TTL_MS = 30 * 24 * 60 * 60 * 1000;
const MISSING_TTL_MS = 24 * 60 * 60 * 1000;
const BATCH = 75;
const API = 'https://api.scryfall.com/cards/collection';
// Scryfall asks for both, and for no more than ten requests a second.
const HEADERS = { 'User-Agent': 'chickatrice.net/1.0', Accept: 'application/json', 'Content-Type': 'application/json' };
const SPACING_MS = 100;

const frontFace = (name: string) => name.split(' // ')[0].trim().toLowerCase();
const isScryfallId = (id: string | undefined): id is string => Boolean(id && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(id));

/** What a card's image is kept under, and found by in the record `cardImages` returns. */
export function cardKey(card: CardRef): string {
  if (isScryfallId(card.providerId)) return `id:${card.providerId.toLowerCase()}`;
  const name = frontFace(card.name);
  return (card.set ? `name:${name}|${card.set.toLowerCase()}` : `name:${name}`).slice(0, 255);
}

type Identifier = { id: string } | { name: string; set?: string };

function identifier(card: CardRef): Identifier {
  if (isScryfallId(card.providerId)) return { id: card.providerId };
  const name = card.name.split(' // ')[0].trim();
  return card.set ? { name, set: card.set } : { name };
}

/**
 * Images for whichever of `cards` Scryfall knows, keyed by `cardKey`. Never
 * throws: if Scryfall can't be reached, the cards it would have found are
 * just left out (and not remembered as missing).
 */
export async function cardImages(cards: CardRef[]): Promise<Record<string, CardImage>> {
  const wanted = new Map<string, CardRef>();
  for (const card of cards) if (card.name) wanted.set(cardKey(card), card);
  if (!wanted.size) return {};

  const result: Record<string, CardImage> = {};
  const stale: CardRef[] = [];
  try {
    const now = Date.now();
    const rows = new Map<string, typeof cache.$inferSelect>();
    const keys = [ ...wanted.keys() ];
    for (let i = 0; i < keys.length; i += 1000)
      for (const row of await db.select().from(cache).where(inArray(cache.key, keys.slice(i, i + 1000)))) rows.set(row.key, row);

    for (const [ key, card ] of wanted) {
      const row = rows.get(key);
      const age = row ? now - row.fetchedAt.getTime() : Infinity;
      if (row?.normal && age < FOUND_TTL_MS) result[key] = { normal: row.normal, artCrop: row.artCrop, artist: row.artist, uri: row.uri };
      else if (!row || row.normal || age >= MISSING_TTL_MS) stale.push(card);
    }

    if (stale.length) {
      const fetched = await lookUp(stale);
      await remember(stale, fetched);
      for (const [ key, image ] of fetched) if (image) result[key] = image;
    }
  } catch (e) {
    console.error('[scryfall] card images', e);
  }
  return result;
}

/** Asks Scryfall about `cards`. Null for those it doesn't know; absent for those it couldn't be asked about. */
async function lookUp(cards: CardRef[]): Promise<Map<string, CardImage | null>> {
  const found = new Map<string, CardImage | null>();
  const ask = async (batch: CardRef[]) => {
    for (let i = 0; i < batch.length; i += BATCH) {
      const chunk = batch.slice(i, i + BATCH);
      const data = await collection(chunk.map(identifier));
      if (!data) continue; // unreachable: leave these out, rather than remember them as missing
      for (const card of chunk) found.set(cardKey(card), match(card, data));
    }
  };

  await ask(cards);
  // Cockatrice's set codes don't always match Scryfall's; any printing beats none.
  const retry = cards.filter((card) => card.set && !isScryfallId(card.providerId) && found.get(cardKey(card)) === null);
  if (retry.length) {
    const byName = new Map(retry.map((card) => [ cardKey(card), { name: card.name } ]));
    await ask([ ...byName.values() ]);
    for (const [ key, nameOnly ] of byName) {
      const image = found.get(cardKey(nameOnly));
      if (image === undefined) found.delete(key); // unreachable the second time
      else found.set(key, image);
    }
  }
  return found;
}

type ScryfallCard = {
  id: string;
  name: string;
  set: string;
  artist?: string;
  scryfall_uri?: string;
  image_uris?: { normal?: string; art_crop?: string };
  card_faces?: { name: string; artist?: string; image_uris?: { normal?: string; art_crop?: string } }[];
};

/** The card Scryfall sent back for `card`, as an image. Its answers aren't in a dependable order, so they're matched up. */
function match(card: CardRef, data: ScryfallCard[]): CardImage | null {
  const set = card.set?.toLowerCase();
  const hit = isScryfallId(card.providerId)
    ? data.find((d) => d.id === card.providerId!.toLowerCase())
    : data.find((d) => frontFace(d.card_faces?.[0]?.name ?? d.name) === frontFace(card.name) && (!set || d.set === set));
  const face = hit?.card_faces?.[0];
  const images = hit?.image_uris ?? face?.image_uris;
  if (!hit || !images?.normal) return null;
  return { normal: images.normal, artCrop: images.art_crop ?? null, artist: hit.artist ?? face?.artist ?? null, uri: hit.scryfall_uri ?? null };
}

// One request at a time, a little apart, across everyone viewing decks.
let queue: Promise<unknown> = Promise.resolve();
const pause = () => new Promise((resolve) => setTimeout(resolve, SPACING_MS));

/** The cards Scryfall found, or null if it couldn't be asked. */
function collection(identifiers: Identifier[]): Promise<ScryfallCard[] | null> {
  const run = queue.then(async () => {
    try {
      const response = await fetch(API, {
        method: 'POST',
        headers: HEADERS,
        body: JSON.stringify({ identifiers }),
        signal: AbortSignal.timeout(10_000),
      });
      if (!response.ok) {
        console.error(`[scryfall] collection: HTTP ${response.status}`);
        return null;
      }
      return ((await response.json()) as { data?: ScryfallCard[] }).data ?? [];
    } catch (e) {
      console.error('[scryfall] collection', e);
      return null;
    }
  });
  queue = run.then(pause);
  return run;
}

async function remember(cards: CardRef[], fetched: Map<string, CardImage | null>) {
  const rows = cards.flatMap((card) => {
    const key = cardKey(card);
    if (!fetched.has(key)) return [];
    const image = fetched.get(key);
    return [ {
      key,
      normal: image?.normal.slice(0, 255) ?? null,
      artCrop: image?.artCrop?.slice(0, 255) ?? null,
      artist: image?.artist?.slice(0, 255) ?? null,
      uri: image?.uri?.slice(0, 512) ?? null,
      fetchedAt: sql`UTC_TIMESTAMP()`,
    } ];
  });
  const unique = [ ...new Map(rows.map((row) => [ row.key, row ])).values() ];
  if (!unique.length) return;
  await db.insert(cache).values(unique).onDuplicateKeyUpdate({
    set: {
      normal: sql`VALUES(${cache.normal})`,
      artCrop: sql`VALUES(${cache.artCrop})`,
      artist: sql`VALUES(${cache.artist})`,
      uri: sql`VALUES(${cache.uri})`,
      fetchedAt: sql`VALUES(${cache.fetchedAt})`,
    },
  });
}
