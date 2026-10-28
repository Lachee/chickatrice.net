import { randomInt } from 'node:crypto';
import { and, eq, inArray, sql } from 'drizzle-orm';
import { db, chickatrice } from '#lib/server/db/index.ts';
import type { DeckVisibility } from '#lib/decks.ts';
import { MAX_DECK_BYTES, MAX_DECK_NAME, cardCount, parseCod, type DeckList } from '#lib/server/decks/format.ts';
import { cardImages, cardKey, type CardRef } from '#lib/server/scryfall.ts';
import { capabilities } from './capabilities';
import { unshareDeck } from './shares';
import { execute, rows, table } from './sql';

/*
 * Decks live where the client's server deck storage keeps them, Servatrice's
 * `decklist_files` (with `decklist_folders`), so a deck added here is there the
 * next time its owner opens the client, and the other way round. What the site
 * adds, the share link and who may follow it, is in `chickatrice_decks`.
 *
 * On schema 37+ the client can make decks (or whole folders) public too, so
 * there "public" is Servatrice's `is_public`, inherited from folders the way
 * `isDeckEffectivelyPublic` in serversocketinterface.cpp works it out, and our
 * own column only tells unlisted from private. On older schemas it's all ours.
 */

const T = {
  files: table('decklist_files'),
  folders: table('decklist_folders'),
  users: table('users'),
};

const { decks } = chickatrice;

/** Thrown for what the person can fix; the message is for them. */
export class DeckError extends Error {}

export type Deck = {
  id: number;
  ownerId: number;
  ownerName: string;
  /** The name in the client's deck storage. */
  name: string;
  /** Where it is in the client's deck storage, like `Modern/Burn`; empty at the top. */
  folder: string;
  /** Epoch seconds. Servatrice stamps it with `NOW()`. */
  uploadedAt: number;
  slug: string;
  visibility: DeckVisibility;
  /** The public folder that makes the deck public in the client, whatever the deck says itself. */
  publicFolder: string | null;
  sourceUrl: string | null;
};

type FileRow = { id: number; ownerId: number; ownerName: string; folderId: number; name: string; uploadedAt: number; isPublic?: number; content: string };
type Folder = { id: number; parent: number; name: string; isPublic: boolean };

async function fileColumns(): Promise<string> {
  const { publicDecks } = await capabilities();
  return `f.id, f.id_user AS ownerId, u.name AS ownerName, f.id_folder AS folderId, f.name,
    UNIX_TIMESTAMP(f.upload_time) AS uploadedAt, f.content${publicDecks ? ', f.is_public AS isPublic' : ''}`;
}

async function foldersOf(ownerId: number): Promise<Map<number, Folder>> {
  const { publicDecks } = await capabilities();
  const result = await rows<{ id: number; parent: number; name: string; isPublic?: number }>(
    `SELECT id, id_parent AS parent, name${publicDecks ? ', is_public AS isPublic' : ''} FROM ${T.folders} WHERE id_user = ?`,
    [ ownerId ],
  );
  return new Map(result.map((f) => [ Number(f.id), { id: Number(f.id), parent: Number(f.parent), name: String(f.name), isPublic: Number(f.isPublic) === 1 } ]));
}

/** The folders a deck is in, outermost first. The guard is Servatrice's, against a loop in the table. */
function ancestors(folders: Map<number, Folder>, folderId: number): Folder[] {
  const path: Folder[] = [];
  for (let id = folderId, guard = 0; id !== 0 && guard < 100; guard++) {
    const folder = folders.get(id);
    if (!folder) break;
    path.unshift(folder);
    id = folder.parent;
  }
  return path;
}

type OurRow = typeof decks.$inferSelect;

function toDeck(file: FileRow, row: OurRow, folders: Map<number, Folder>): Deck {
  const path = ancestors(folders, Number(file.folderId));
  const pathTo = (end: number) => path.slice(0, end).map((folder) => folder.name).join('/');
  const isPublic = file.isPublic === undefined ? null : Number(file.isPublic) === 1;
  const publicAt = isPublic === null ? -1 : path.findLastIndex((folder) => folder.isPublic);

  let visibility: DeckVisibility = row.visibility;
  if (isPublic !== null)
    visibility = isPublic || publicAt >= 0 ? 'public' : row.visibility === 'unlisted' ? 'unlisted' : 'private';

  return {
    id: Number(file.id),
    ownerId: Number(file.ownerId),
    ownerName: String(file.ownerName),
    name: String(file.name),
    folder: pathTo(path.length),
    uploadedAt: Number(file.uploadedAt),
    slug: row.slug,
    visibility,
    publicFolder: publicAt >= 0 ? pathTo(publicAt + 1) : null,
    sourceUrl: row.sourceUrl,
  };
}

/** Lower-case letters and digits: our column compares without case, so mixed case would only look like more. */
function newSlug(): string {
  const alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
  return Array.from({ length: 12 }, () => alphabet[randomInt(alphabet.length)]).join('');
}

/**
 * Our rows for these decks, making any that are missing. A row left by a
 * deleted deck whose id has since gone to someone else's is replaced.
 */
async function ensureRows(files: Pick<FileRow, 'id' | 'ownerId'>[]): Promise<Map<number, OurRow>> {
  if (!files.length) return new Map();
  const ids = files.map((file) => Number(file.id));
  const existing = new Map((await db.select().from(decks).where(inArray(decks.deckId, ids))).map((row) => [ row.deckId, row ]));

  const stale = files.filter((file) => {
    const row = existing.get(Number(file.id));
    return row && row.accountId !== Number(file.ownerId);
  });
  if (stale.length) {
    await db.delete(decks).where(inArray(decks.deckId, stale.map((file) => Number(file.id))));
    for (const file of stale) existing.delete(Number(file.id));
  }

  const missing = files.filter((file) => !existing.has(Number(file.id)));
  if (!missing.length) return existing;
  // IGNORE: another request may be making the same rows.
  await db.insert(decks).ignore().values(missing.map((file) => ({
    deckId: Number(file.id),
    accountId: Number(file.ownerId),
    slug: newSlug(),
    createdAt: sql`UTC_TIMESTAMP()`,
  })));
  for (const row of await db.select().from(decks).where(inArray(decks.deckId, missing.map((file) => Number(file.id)))))
    existing.set(row.deckId, row);
  return existing;
}

/** Everything `ownerId` keeps, newest first, with each deck's contents. */
export async function listDecks(ownerId: number): Promise<(Deck & { content: string })[]> {
  const [ files, folders ] = await Promise.all([
    rows<FileRow>(`SELECT ${await fileColumns()} FROM ${T.files} f JOIN ${T.users} u ON u.id = f.id_user
      WHERE f.id_user = ? ORDER BY f.upload_time DESC, f.id DESC`, [ ownerId ]),
    foldersOf(ownerId),
  ]);
  const ours = await ensureRows(files);
  return files.flatMap((file) => {
    const row = ours.get(Number(file.id));
    return row ? [ { ...toDeck(file, row, folders), content: String(file.content) } ] : [];
  });
}

export async function countDecks(ownerId: number): Promise<number> {
  const [ row ] = await rows<{ n: number }>(`SELECT COUNT(*) AS n FROM ${T.files} WHERE id_user = ?`, [ ownerId ]);
  return Number(row?.n ?? 0);
}

export async function findDeckBySlug(slug: string): Promise<(Deck & { content: string }) | null> {
  if (!/^[a-z0-9]{1,16}$/.test(slug)) return null;
  const [ row ] = await db.select().from(decks).where(eq(decks.slug, slug)).limit(1);
  if (!row) return null;
  const [ file ] = await rows<FileRow>(
    `SELECT ${await fileColumns()} FROM ${T.files} f JOIN ${T.users} u ON u.id = f.id_user WHERE f.id = ?`,
    [ row.deckId ],
  );
  // Gone, or the id now belongs to someone else's deck.
  if (!file || Number(file.ownerId) !== row.accountId) return null;
  return { ...toDeck(file, row, await foldersOf(row.accountId)), content: String(file.content) };
}

/** Adds a deck at the top of `ownerId`'s deck storage. Returns its slug. */
export async function createDeck(
  ownerId: number,
  deck: Pick<DeckList, 'name' | 'bannerCard' | 'tags'> & { content: string },
  sourceUrl: string | null = null,
): Promise<string> {
  if (Buffer.byteLength(deck.content, 'utf8') > MAX_DECK_BYTES)
    throw new DeckError(`That deck is too big to keep: the server's limit is ${Math.floor(MAX_DECK_BYTES / 1024)} KB.`);

  const { publicDecks } = await capabilities();
  const name = deck.name.slice(0, MAX_DECK_NAME);
  // As `cmdDeckUpload` writes it, preview columns included. The colour identity
  // comes from the client's card database, which the site doesn't have.
  const result = publicDecks
    ? await execute(
      `INSERT INTO ${T.files} (id_folder, id_user, name, upload_time, content, is_public, banner_card_name, banner_card_provider, color_identity, tags)
       VALUES (0, ?, ?, NOW(), ?, 0, ?, ?, '', ?)`,
      [ ownerId, name, deck.content, deck.bannerCard?.name.slice(0, 255) ?? '', deck.bannerCard?.providerId?.slice(0, 32) ?? '', JSON.stringify(deck.tags ?? []) ],
    )
    : await execute(
      `INSERT INTO ${T.files} (id_folder, id_user, name, upload_time, content) VALUES (0, ?, ?, NOW(), ?)`,
      [ ownerId, name, deck.content ],
    );

  const slug = newSlug();
  await db.insert(decks).values({
    deckId: result.insertId,
    accountId: ownerId,
    slug,
    sourceUrl: sourceUrl?.slice(0, 512) ?? null,
    createdAt: sql`UTC_TIMESTAMP()`,
  }).onDuplicateKeyUpdate({ set: { accountId: ownerId, slug, visibility: 'private', sourceUrl: sourceUrl?.slice(0, 512) ?? null } });
  return slug;
}

/** A new name and contents (the comments changed, say), leaving where it is and who can see it. */
export async function updateDeck(deck: Pick<Deck, 'id' | 'ownerId'>, name: string, content: string): Promise<void> {
  if (Buffer.byteLength(content, 'utf8') > MAX_DECK_BYTES)
    throw new DeckError(`That's too much to keep: the server's limit is ${Math.floor(MAX_DECK_BYTES / 1024)} KB.`);
  await execute(`UPDATE ${T.files} SET name = ?, content = ? WHERE id = ? AND id_user = ?`, [ name.slice(0, MAX_DECK_NAME), content, deck.id, deck.ownerId ]);
}

/**
 * On schema 37+, public is the deck's own `is_public`. A deck made public by
 * its folder stays public whatever is set here; the page says so. Making it
 * private ends its "Open in Cockatrice" share, which anyone holding could follow.
 */
export async function setDeckVisibility(deck: Pick<Deck, 'id' | 'ownerId'>, visibility: DeckVisibility): Promise<void> {
  await db.update(decks).set({ visibility }).where(and(eq(decks.deckId, deck.id), eq(decks.accountId, deck.ownerId)));
  if ((await capabilities()).publicDecks)
    await execute(`UPDATE ${T.files} SET is_public = ? WHERE id = ? AND id_user = ?`, [ visibility === 'public' ? 1 : 0, deck.id, deck.ownerId ]);
  if (visibility === 'private') await unshareDeck(deck.id);
}

export async function deleteDeck(deck: Pick<Deck, 'id' | 'ownerId'>): Promise<void> {
  await execute(`DELETE FROM ${T.files} WHERE id = ? AND id_user = ?`, [ deck.id, deck.ownerId ]);
  await db.delete(decks).where(eq(decks.deckId, deck.id));
  await unshareDeck(deck.id);
}

/** A row of a deck list page. */
export type DeckSummary = Pick<Deck, 'slug' | 'name' | 'folder' | 'uploadedAt' | 'visibility' | 'publicFolder'> & {
  /** Null when the client saved something the site can't read. */
  cards: number | null;
  /** The card whose art stands for the deck: its banner card, or else the first in the main deck. */
  cover: CardRef | null;
};

export function summarize(deck: Deck & { content: string }): DeckSummary {
  const parsed = parseCod(deck.content);
  const { slug, name, folder, uploadedAt, visibility, publicFolder } = deck;
  const first = (parsed?.zones.find((zone) => zone.name === 'main') ?? parsed?.zones[0])?.cards[0];
  const banner = parsed?.bannerCard;
  return {
    slug, name, folder, uploadedAt, visibility, publicFolder,
    cards: parsed ? cardCount(parsed) : null,
    cover: banner ? { name: banner.name, providerId: banner.providerId }
      : first ? { name: first.name, providerId: first.providerId, set: first.set } : null,
  };
}

export type CoverArt = { url: string; artist: string | null };

/** Each deck's cover art, by slug, for the decks whose cover Scryfall knows. */
export async function coverArt(decks: DeckSummary[]): Promise<Record<string, CoverArt>> {
  const images = await cardImages(decks.flatMap((deck) => (deck.cover ? [ deck.cover ] : [])));
  return Object.fromEntries(decks.flatMap((deck) => {
    const image = deck.cover && images[cardKey(deck.cover)];
    return image?.artCrop ? [ [ deck.slug, { url: image.artCrop, artist: image.artist } ] ] : [];
  }));
}

/** The decks of `ownerId` anyone may see listed; or all of them, for its owner and admins. */
export async function listDecksOf(ownerId: number, everything: boolean): Promise<(Deck & { content: string })[]> {
  const all = await listDecks(ownerId);
  return everything ? all : all.filter((deck) => deck.visibility === 'public');
}

/** Drops our rows for decks that are gone (deleted in the client, or with their account). */
export async function pruneDeckRows(): Promise<number> {
  const result = await execute(
    `DELETE d FROM chickatrice_decks d LEFT JOIN ${T.files} f ON f.id = d.deck_id AND f.id_user = d.account_id WHERE f.id IS NULL`,
  );
  return result.affectedRows;
}
