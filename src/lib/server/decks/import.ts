import https from 'node:https';
import type { DeckCard, DeckList } from './format.ts';

/*
 * Fetching a deck from a deck-building site by its URL. Only sites with a JSON
 * API the server can reach: MTGGoldfish, TappedOut and the like sit behind
 * Cloudflare challenges, so for those the page says to paste a text export.
 * The URLs fetched are built here from an id, never taken from the user.
 */

/** Something the person importing can act on; its message is shown to them. */
export class ImportError extends Error {}

export type Imported = { deck: DeckList; sourceUrl: string };

// Moxfield's Cloudflare turns away a user agent with a URL in it.
const USER_AGENT = 'chickatrice.net/1.0';
/** Decks are tens of kilobytes; this is plenty. */
const MAX_RESPONSE_BYTES = 10 * 1024 * 1024;

type Importer = {
  name: string;
  /** The deck's id, if `url` is one of this site's decks. */
  match: (url: URL) => string | null;
  fetch: (id: string) => Promise<Imported>;
};

const IMPORTERS: Importer[] = [
  {
    name: 'Moxfield',
    match: (url) => (/(^|\.)moxfield\.com$/.test(url.hostname) && url.pathname.match(/^\/decks\/([\w-]+)/)?.[1]) || null,
    fetch: importMoxfield,
  },
  {
    name: 'Archidekt',
    match: (url) => (/(^|\.)archidekt\.com$/.test(url.hostname) && url.pathname.match(/^\/decks\/(\d+)/)?.[1]) || null,
    fetch: importArchidekt,
  },
];

export const importerNames = IMPORTERS.map((importer) => importer.name);

export async function importFromUrl(input: string): Promise<Imported> {
  let url: URL;
  try {
    url = new URL(/^https?:\/\//i.test(input) ? input : `https://${input}`);
  } catch {
    throw new ImportError("That isn't a link.");
  }
  for (const importer of IMPORTERS) {
    const id = importer.match(url);
    if (id) return importer.fetch(id);
  }
  throw new ImportError(`Decks can be imported from ${importerNames.join(' and ')}. From anywhere else, export the deck as text and paste it instead.`);
}

/**
 * A GET with `node:https` rather than `fetch`: Moxfield's Cloudflare challenges
 * the headers undici adds (`sec-fetch-mode` and friends), but not this.
 */
function get(url: string): Promise<{ status: number; body: string }> {
  return new Promise((resolve, reject) => {
    const request = https.get(url, { headers: { 'User-Agent': USER_AGENT }, timeout: 15_000 }, (response) => {
      const chunks: Buffer[] = [];
      let size = 0;
      response.on('data', (chunk: Buffer) => {
        size += chunk.length;
        if (size > MAX_RESPONSE_BYTES) request.destroy(new Error('response too large'));
        else chunks.push(chunk);
      });
      response.on('end', () => resolve({ status: response.statusCode ?? 0, body: Buffer.concat(chunks).toString('utf8') }));
      response.on('error', reject);
    });
    request.on('timeout', () => request.destroy(new Error('timed out')));
    request.on('error', reject);
  });
}

async function getJson(service: string, url: string): Promise<any> {
  let response: { status: number; body: string };
  try {
    response = await get(url);
  } catch {
    throw new ImportError(`${service} didn't answer. Try again in a moment.`);
  }
  if (response.status === 404)
    throw new ImportError(`${service} has no deck at that link, or it's private.`);
  if (response.status !== 200)
    throw new ImportError(`${service} turned the request down (HTTP ${response.status}). If the deck is private there, share it first; otherwise export it as text and paste it instead.`);
  try {
    return JSON.parse(response.body);
  } catch {
    throw new ImportError(`${service} sent back something that isn't a deck. Export the deck as text and paste it instead.`);
  }
}

/**
 * Cockatrice knows most two-faced cards by their front face, but split cards
 * (and aftermath, and rooms, which Scryfall calls split) by both halves.
 */
function cockatriceName(name: string, layout?: string): string {
  if (!name.includes(' // ') || layout === 'split' || layout === 'aftermath') return name;
  return name.split(' // ')[0];
}

const str = (value: unknown) => (typeof value === 'string' && value ? value : undefined);

async function importMoxfield(id: string): Promise<Imported> {
  const mox = await getJson('Moxfield', `https://api2.moxfield.com/v3/decks/all/${encodeURIComponent(id)}`);
  if (!mox?.boards) throw new ImportError("Moxfield sent back something that isn't a deck.");

  // Its other boards (maybe, attractions, stickers, ...) aren't part of a game Cockatrice plays.
  const boards: [string, string][] = [
    [ 'commanders', 'main' ], [ 'mainboard', 'main' ], [ 'signatureSpells', 'main' ],
    [ 'companions', 'side' ], [ 'sideboard', 'side' ],
    [ 'tokens', 'tokens' ],
  ];
  const zones = new Map<string, DeckCard[]>();
  for (const [ board, zone ] of boards) {
    for (const entry of Object.values<any>(mox.boards[board]?.cards ?? {})) {
      const card = entry?.card;
      if (!str(card?.name) || !(entry.quantity > 0)) continue;
      if (!zones.has(zone)) zones.set(zone, []);
      zones.get(zone)!.push({
        name: cockatriceName(card.name, card.layout),
        count: entry.quantity,
        set: str(card.set)?.toUpperCase(),
        collectorNumber: str(card.cn),
        providerId: str(card.scryfall_id),
      });
    }
  }

  const featured = mox.main;
  return {
    deck: {
      name: str(mox.name) ?? '',
      comments: str(mox.description) ?? '',
      zones: [ ...zones ].map(([ name, cards ]) => ({ name, cards })),
      bannerCard: str(featured?.name)
        ? { name: cockatriceName(featured.name, featured.layout), providerId: str(featured.scryfall_id) }
        : undefined,
      // Moxfield's deck tags; its `authorTags` are per card.
      tags: Array.isArray(mox.hubs) ? mox.hubs.map((hub: any) => str(hub?.name)).filter(Boolean) : undefined,
    },
    sourceUrl: str(mox.publicUrl) ?? `https://moxfield.com/decks/${id}`,
  };
}

async function importArchidekt(id: string): Promise<Imported> {
  const deck = await getJson('Archidekt', `https://archidekt.com/api/decks/${id}/`);
  if (!Array.isArray(deck?.cards)) throw new ImportError("Archidekt sent back something that isn't a deck.");

  // Archidekt files every card under categories, and a card is in the deck if
  // its first one is. "Sideboard" and "Maybeboard" are left out by default.
  const outside = new Set<string>(
    (deck.categories ?? []).filter((category: any) => category?.includedInDeck === false).map((category: any) => category.name),
  );
  const main: DeckCard[] = [];
  const side: DeckCard[] = [];
  let commander: DeckCard | undefined;
  for (const entry of deck.cards) {
    const card = entry?.card;
    const name = str(card?.oracleCard?.name);
    if (!name || !(entry.quantity > 0)) continue;
    const categories: string[] = Array.isArray(entry.categories) ? entry.categories : [];
    const zone = categories.includes('Sideboard') ? side : outside.has(categories[0]) ? null : main;
    const parsed: DeckCard = {
      name: cockatriceName(name, card.oracleCard.layout),
      count: entry.quantity,
      set: str(card.edition?.editioncode)?.toUpperCase(),
      collectorNumber: str(card.collectorNumber),
      providerId: str(card.uid),
    };
    zone?.push(parsed);
    if (!commander && categories.includes('Commander')) commander = parsed;
  }

  // Its description is either plain text or its editor's JSON; only the first is any use here.
  const description = str(deck.description) ?? '';
  return {
    deck: {
      name: str(deck.name) ?? '',
      comments: /^\s*[[{]/.test(description) ? '' : description,
      zones: [ { name: 'main', cards: main }, { name: 'side', cards: side } ],
      bannerCard: commander && { name: commander.name, providerId: commander.providerId },
      tags: Array.isArray(deck.deckTags) ? deck.deckTags.map((tag: any) => str(tag?.name) ?? str(tag)).filter(Boolean) : undefined,
    },
    sourceUrl: `https://archidekt.com/decks/${id}`,
  };
}
