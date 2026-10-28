import { cardImages, cardKey } from '#lib/server/scryfall.ts';
import { capabilities } from './capabilities';
import { execute, rows, table } from './sql';

/*
 * A player's banner: the card whose art Cockatrice shows behind their name
 * (schema 35+). Servatrice keeps it in `users.card_art_params` as JSON, written
 * by `cmdSetCardArtParams` and read into `ServerInfo_User` at login:
 *
 *   {"card_name":"…","card_provider_id":"<scryfall id>","marginPctL":0.33,
 *    "marginPctR":0.02,"verticalOffset":0.35,"zoom":1}
 *
 * The numbers place the art in the client's user list; the site's banner only
 * uses `verticalOffset` and `zoom`. Staff can deny a card with a card art rule
 * (settings.ts). A player who is connected when it changes sees it at their
 * next login, as do the players they meet.
 */

const T = { users: table('users'), rules: table('card_art_name_rules') };

export type Banner = {
  /** Cockatrice's name for the card: see `cockatriceName`. */
  cardName: string;
  /** The printing's Scryfall id; may be empty. */
  providerId: string;
  marginPctL: number;
  marginPctR: number;
  verticalOffset: number;
  zoom: number;
};

/** `CardArtParams`' defaults, which is what a new banner from the site gets. */
const PLACEMENT = { marginPctL: 0.33, marginPctR: 0.02, verticalOffset: 0.35, zoom: 1 };

/** Into the range `cmdSetCardArtParams` clamps to, or the default if it isn't a number. */
const clamp = (value: unknown, min: number, max: number, fallback: number) =>
  typeof value === 'number' && Number.isFinite(value) ? Math.min(max, Math.max(min, value)) : fallback;

function parse(json: unknown): Banner | null {
  if (typeof json !== 'string' || !json) return null;
  let params: any;
  try {
    params = JSON.parse(json);
  } catch {
    return null;
  }
  if (typeof params?.card_name !== 'string' || !params.card_name) return null;
  return {
    cardName: params.card_name,
    providerId: typeof params.card_provider_id === 'string' ? params.card_provider_id : '',
    marginPctL: clamp(params.marginPctL, 0, 0.95, PLACEMENT.marginPctL),
    marginPctR: clamp(params.marginPctR, 0, 0.95, PLACEMENT.marginPctR),
    verticalOffset: clamp(params.verticalOffset, 0, 1, PLACEMENT.verticalOffset),
    zoom: clamp(params.zoom, 0.1, 4, PLACEMENT.zoom),
  };
}

export async function findBanner(accountId: number): Promise<Banner | null> {
  if (!(await capabilities()).cardArt) return null;
  const [ row ] = await rows<{ params: string | null }>(`SELECT card_art_params AS params FROM ${T.users} WHERE id = ?`, [ accountId ]);
  return parse(row?.params);
}

/**
 * As `isCardArtAllowed`: only a DENY rule for that card and printing refuses
 * it. Names compare without case, by the column's collation.
 */
export async function bannerAllowed(cardName: string, providerId: string): Promise<boolean> {
  if (!(await capabilities()).cardArtRules) return true;
  const [ rule ] = await rows<{ mode: string }>(
    `SELECT mode FROM ${T.rules} WHERE card_name = ? AND card_provider_id = ?`, [ cardName, providerId ]);
  return rule?.mode !== 'DENY';
}

/**
 * As `cmdSetCardArtParams`, which also clears it with null. A new card is
 * placed the default way: what suited the last one's art needn't suit this one's.
 */
export async function setBanner(accountId: number, card: Pick<Banner, 'cardName' | 'providerId'> | null): Promise<void> {
  if (!card) {
    await execute(`UPDATE ${T.users} SET card_art_params = NULL WHERE id = ?`, [ accountId ]);
    return;
  }
  // Servatrice's QJsonObject writes the keys sorted, which these already are.
  const params = JSON.stringify({ card_name: card.cardName, card_provider_id: card.providerId, ...PLACEMENT });
  await execute(`UPDATE ${T.users} SET card_art_params = ? WHERE id = ?`, [ params, accountId ]);
}

/** The art a banner shows, with what to credit. */
export type BannerArt = { url: string; artist: string | null; uri: string | null };

/** Its art from Scryfall, or null if Scryfall has none for it (or can't be reached). Never throws. */
export async function bannerArt(banner: Pick<Banner, 'cardName' | 'providerId'>): Promise<BannerArt | null> {
  const card = { name: banner.cardName, providerId: banner.providerId || undefined };
  const image = (await cardImages([ card ]))[cardKey(card)];
  return image?.artCrop ? { url: image.artCrop, artist: image.artist, uri: image.uri } : null;
}
