import { createHash, randomBytes } from 'node:crypto';
import { eq, sql } from 'drizzle-orm';
import { config } from '#lib/server/config.ts';
import { db, chickatrice, pool } from '#lib/server/db/index.ts';
import type { DeckList } from '#lib/server/decks/format.ts';
import { capabilities } from './capabilities';
import type { Deck } from './decks';
import { execute, rows, table } from './sql';

/*
 * "Open in Cockatrice": a `cockatrice://opendeck` link, which the client follows
 * by connecting to the server and fetching the deck with the link's token. The
 * token is a Servatrice deck share (`deck_share`, schema 37+), written the way
 * `cmdDeckShareCreate` in serversocketinterface.cpp writes one for a single deck.
 *
 * The site's shares have no `created_by`: they'd otherwise show up in that
 * player's list of their own shares in the client, and count towards
 * Servatrice's shares-per-day limit, for links they never made. They last a day,
 * long enough for the click that asked for one, and are handed out again while
 * they last so each click doesn't make another. Servatrice deletes them once
 * they expire; making a deck private or deleting it deletes its share at once.
 */

const T = {
  shares: table('deck_share'),
  items: table('deck_share_item'),
  files: table('decklist_files'),
};

const { deckShares } = chickatrice;

const SHARE_DAYS = 1;
/** A share with less left than this isn't handed out again: the link may sit in the client's login prompt a while. */
const MIN_HOURS_LEFT = 1;
/** `deck_share.name` and `deck_share_item.name`. */
const MAX_NAME = 50;

const hashOf = (content: string) => createHash('sha256').update(content, 'utf8').digest('hex');

/** As `generateShareToken`: 32 random bytes, URL-safe base64 without padding. */
const newToken = () => randomBytes(32).toString('base64url');

/** As `sanitizeColorIdentity`: the WUBRG letters, once each. */
const colorIdentity = (value: unknown) => [ ...new Set(String(value ?? '').toUpperCase()) ].filter((c) => 'WUBRG'.includes(c)).join('');

/** The link that opens `token`'s decks in the client, on this server. */
export function openDeckLink(token: string): string {
  const query = new URLSearchParams({ share: token, hostname: config.server.host, port: String(config.server.port) });
  return `cockatrice://opendeck?${query}`;
}

/** A token for `deck` the client can fetch it by: the last one, while it lasts and the deck is unchanged. */
export async function shareDeck(deck: Pick<Deck, 'id' | 'name'> & { content: string }, parsed: DeckList): Promise<string> {
  const contentHash = hashOf(deck.content);
  const [ ours ] = await db.select().from(deckShares).where(eq(deckShares.deckId, deck.id)).limit(1);
  if (ours?.contentHash === contentHash) {
    const [ live ] = await rows<{ token: string }>(
      `SELECT token FROM ${T.shares} WHERE id = ? AND created_by IS NULL AND expires_at > DATE_ADD(NOW(), INTERVAL ? HOUR)`,
      [ ours.shareId, MIN_HOURS_LEFT ],
    );
    if (live) return String(live.token);
  }

  const { publicDecks } = await capabilities();
  const [ file ] = publicDecks
    ? await rows<{ colors: string | null }>(`SELECT color_identity AS colors FROM ${T.files} WHERE id = ?`, [ deck.id ])
    : [];
  const name = (deck.name.trim() || 'Unnamed deck').slice(0, MAX_NAME);
  const token = newToken();

  const connection = await pool.getConnection();
  let shareId: number;
  try {
    await connection.beginTransaction();
    const [ result ] = await connection.query(
      `INSERT INTO ${T.shares} (token, name, created_by, created_at, expires_at)
       VALUES (?, ?, NULL, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY))`,
      [ token, name, SHARE_DAYS ],
    );
    shareId = (result as { insertId: number }).insertId;
    // Empty columns are NULL, as Qt's driver binds Servatrice's empty strings.
    await connection.query(
      `INSERT INTO ${T.items} (share_id, name, tags, banner_card, game_format, color_identity, content, position)
       VALUES (?, ?, ?, ?, ?, ?, ?, 0)`,
      [
        shareId, name, JSON.stringify(parsed.tags ?? []), parsed.bannerCard?.name.slice(0, 255) || null,
        parsed.format?.slice(0, 50) || null, colorIdentity(file?.colors) || null, deck.content,
      ],
    );
    await connection.commit();
  } catch (e) {
    await connection.rollback();
    throw e;
  } finally {
    connection.release();
  }

  // The share it replaces is left to expire: someone may be opening it right now.
  await db.insert(deckShares).values({ deckId: deck.id, shareId, contentHash, createdAt: sql`UTC_TIMESTAMP()` })
    .onDuplicateKeyUpdate({ set: { shareId, contentHash, createdAt: sql`UTC_TIMESTAMP()` } });
  return token;
}

/** Stops `deckId`'s link working, when the deck goes private or away. Its items go with it (`ON DELETE CASCADE`). */
export async function unshareDeck(deckId: number): Promise<void> {
  const [ ours ] = await db.select().from(deckShares).where(eq(deckShares.deckId, deckId)).limit(1);
  if (!ours) return;
  if ((await capabilities()).deckShares)
    await execute(`DELETE FROM ${T.shares} WHERE id = ? AND created_by IS NULL`, [ ours.shareId ]);
  await db.delete(deckShares).where(eq(deckShares.deckId, deckId));
}

/** Drops our rows for shares Servatrice has deleted, and for decks we no longer have. */
export async function pruneDeckShareRows(): Promise<number> {
  const { deckShares: shares } = await capabilities();
  const result = await execute(
    `DELETE s FROM chickatrice_deck_shares s LEFT JOIN chickatrice_decks d ON d.deck_id = s.deck_id
     ${shares ? `LEFT JOIN ${T.shares} ds ON ds.id = s.share_id` : ''}
     WHERE d.deck_id IS NULL${shares ? ' OR ds.id IS NULL' : ''}`,
  );
  return result.affectedRows;
}
