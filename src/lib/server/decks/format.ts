import { createHash } from 'node:crypto';

/*
 * Cockatrice's deck file, `.cod`: what Servatrice keeps in `decklist_files.content`
 * and the client saves to disk. Written by `DeckList::write` in
 * libcockatrice_deck_list/deck_list.cpp:
 *
 *   <cockatrice_deck version="1">
 *     <deckname>…</deckname>
 *     <comments>…</comments>
 *     <bannerCard providerId="…">…</bannerCard>
 *     <tags><tag>…</tag></tags>
 *     <zone name="main">
 *       <card number="4" name="…" setShortName="…" collectorNumber="…" uuid="…"/>
 *     </zone>
 *     <zone name="side">…</zone>
 *   </cockatrice_deck>
 *
 * It's flat and machine-written, so it's read with regular expressions rather
 * than a whole XML parser. Editing a deck's name or comments changes just those
 * elements, so everything this file doesn't know about (sideboard plans,
 * playmats, …) survives.
 */

export type DeckCard = {
  name: string;
  count: number;
  /** Set code, e.g. `M10`. */
  set?: string;
  collectorNumber?: string;
  /** The printing's Scryfall id, which Cockatrice calls the provider id (`uuid`). */
  providerId?: string;
};

export type DeckZone = { name: string; cards: DeckCard[] };

export type DeckList = {
  name: string;
  comments: string;
  zones: DeckZone[];
  /** The card shown on the deck's tile in the client. */
  bannerCard?: { name: string; providerId?: string };
  tags?: string[];
  /** The client's `<format>`, like `commander`. Read only, for deck shares. */
  format?: string;
};

/** Servatrice's `decklist_files.content` is a TEXT column. */
export const MAX_DECK_BYTES = 65535;
/** Servatrice's `decklist_files.name`. */
export const MAX_DECK_NAME = 50;
/** Copies of one card on one line; nobody's deck needs more. */
const MAX_COUNT = 999;

export const cardCount = (deck: Pick<DeckList, 'zones'>) =>
  deck.zones.reduce((total, zone) => total + zone.cards.reduce((n, card) => n + card.count, 0), 0);

// --- XML ---------------------------------------------------------------------

const ENTITIES: Record<string, string> = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'" };

function decode(value: string): string {
  return value.replace(/&(#x[0-9a-f]+|#\d+|amp|lt|gt|quot|apos);/gi, (whole, entity: string) => {
    if (entity[0] !== '#') return ENTITIES[entity.toLowerCase()] ?? whole;
    const code = entity[1].toLowerCase() === 'x' ? parseInt(entity.slice(2), 16) : parseInt(entity.slice(1), 10);
    try { return String.fromCodePoint(code); } catch { return whole; }
  });
}

/** Escaped for text or a double-quoted attribute, minus the control characters XML 1.0 can't hold. */
function encode(value: string): string {
  return value
    .replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f￾￿]/g, '')
    .replace(/[&<>"]/g, (c) => `&${{ '&': 'amp', '<': 'lt', '>': 'gt', '"': 'quot' }[c]};`);
}

function attributes(source: string): Record<string, string> {
  const result: Record<string, string> = {};
  for (const [ , name, double, single ] of source.matchAll(/([\w:-]+)\s*=\s*(?:"([^"]*)"|'([^']*)')/g))
    result[name] = decode(double ?? single);
  return result;
}

/** The first `<tag>`, with its attributes and its contents (`raw`, and decoded as `text`), or null. */
function element(xml: string, tag: string): { attributes: Record<string, string>; raw: string; text: string } | null {
  const match = xml.match(new RegExp(`<${tag}\\b([^>]*?)(?:/>|>([\\s\\S]*?)</${tag}\\s*>)`));
  return match ? { attributes: attributes(match[1]), raw: match[2] ?? '', text: decode(match[2] ?? '') } : null;
}

export const isCod = (content: string) => /<cockatrice_deck\b/.test(content);

/** Reads a `.cod`. Null if it isn't one. */
export function parseCod(xml: string): DeckList | null {
  if (!isCod(xml)) return null;

  const zones: DeckZone[] = [];
  for (const [ , attrs, body ] of xml.matchAll(/<zone\b([^>]*?)(?:\/>|>([\s\S]*?)<\/zone\s*>)/g)) {
    const name = attributes(attrs).name ?? '';
    let zone = zones.find((z) => z.name === name);
    if (!zone) zones.push(zone = { name, cards: [] });
    for (const [ , cardAttrs ] of (body ?? '').matchAll(/<card\b([^>]*?)\/?>/g)) {
      const card = attributes(cardAttrs);
      const count = parseInt(card.number ?? '', 10);
      if (!card.name || !(count > 0)) continue;
      zone.cards.push({
        name: card.name,
        count,
        set: card.setShortName || undefined,
        collectorNumber: card.collectorNumber || undefined,
        providerId: card.uuid || undefined,
      });
    }
  }

  const banner = element(xml, 'bannerCard');
  const tags = element(xml, 'tags');
  return {
    name: element(xml, 'deckname')?.text.trim() ?? '',
    comments: element(xml, 'comments')?.text ?? '',
    zones,
    bannerCard: banner?.text ? { name: banner.text, providerId: banner.attributes.providerId || undefined } : undefined,
    tags: tags ? [ ...tags.raw.matchAll(/<tag>([\s\S]*?)<\/tag>/g) ].map(([ , tag ]) => decode(tag)) : undefined,
    format: element(xml, 'format')?.text.trim() || undefined,
  };
}

/** Writes a new `.cod`, laid out as the client does. */
export function writeCod(deck: DeckList): string {
  const lines = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<cockatrice_deck version="1">',
    `    <deckname>${encode(deck.name)}</deckname>`,
    `    <comments>${encode(deck.comments)}</comments>`,
  ];
  if (deck.bannerCard)
    lines.push(`    <bannerCard providerId="${encode(deck.bannerCard.providerId ?? '')}">${encode(deck.bannerCard.name)}</bannerCard>`);
  if (deck.tags?.length)
    lines.push('    <tags>', ...deck.tags.map((tag) => `        <tag>${encode(tag)}</tag>`), '    </tags>');
  for (const zone of deck.zones) {
    if (!zone.cards.length) continue;
    lines.push(`    <zone name="${encode(zone.name)}">`);
    for (const card of zone.cards) {
      let attrs = `number="${card.count}" name="${encode(card.name)}"`;
      if (card.set) attrs += ` setShortName="${encode(card.set)}"`;
      if (card.collectorNumber) attrs += ` collectorNumber="${encode(card.collectorNumber)}"`;
      if (card.providerId) attrs += ` uuid="${encode(card.providerId)}"`;
      lines.push(`        <card ${attrs}/>`);
    }
    lines.push('    </zone>');
  }
  lines.push('</cockatrice_deck>', '');
  return lines.join('\n');
}

/** Sets a top-level text element of a `.cod`, adding it after the root's opening tag if it's missing. */
function setElement(xml: string, tag: string, value: string): string {
  const replacement = `<${tag}>${encode(value)}</${tag}>`;
  const existing = new RegExp(`<${tag}\\s*/>|<${tag}>[\\s\\S]*?</${tag}\\s*>`);
  if (existing.test(xml)) return xml.replace(existing, () => replacement);
  return xml.replace(/<cockatrice_deck\b[^>]*>/, (open) => `${open}\n    ${replacement}`);
}

/** A `.cod` with a new name and comments, and the rest as it was. */
export function updateCodMetadata(xml: string, { name, comments }: { name: string; comments: string }): string {
  return setElement(setElement(xml, 'comments', comments), 'deckname', name);
}

/**
 * The deck's hash, as the client shows it in games (`DecklistNodeTree::computeDeckHash`):
 * a SHA-1 over every copy's lowercased name, sideboard cards prefixed `SB:`.
 */
export function deckHash(deck: Pick<DeckList, 'zones'>): string {
  const copies: string[] = [];
  for (const zone of deck.zones) {
    if (zone.name !== 'main' && zone.name !== 'side') continue;
    const prefix = zone.name === 'side' ? 'SB:' : '';
    for (const card of zone.cards)
      for (let i = 0; i < card.count; i++) copies.push(prefix + card.name.toLowerCase());
  }
  copies.sort();
  const digest = createHash('sha1').update(copies.join(';'), 'utf8').digest();
  let number = 0n;
  for (let i = 0; i < 5; i++) number |= BigInt(digest[i]) << BigInt(32 - 8 * i);
  return number.toString(32).padStart(8, '0');
}

// --- Plain text ----------------------------------------------------------------

/**
 * Section headings in the text exports of Arena, MTGO, Moxfield, Archidekt and
 * friends, and the zone their cards go in. Cockatrice has no command zone, so
 * commanders join the main deck; null means the section isn't part of the deck.
 */
const SECTIONS: [RegExp, string | null][] = [
  [ /^(deck|decklist|main|maindeck|mainboard|commanders?|signature spells?)$/, 'main' ],
  [ /^(side|sideboard|companions?)$/, 'side' ],
  [ /^tokens?$/, 'tokens' ],
  [ /^(maybe|maybeboard|considering|about|metadata)$/, null ],
];

/** `4 Lightning Bolt`, `4x Lightning Bolt (M10) 146 *F*`, `SB: 2 Smash to Smithereens`, `1 Card|SET`. */
const CARD_LINE = /^(sb:\s*)?(\d{1,4})\s*x?\s+(.+)$/i;

export type ParsedText = { deck: DeckList; skipped: string[] };

/** Reads a plain-text deck list. Lines it can't make sense of are returned in `skipped`. */
export function parseText(text: string): ParsedText {
  const zones = new Map<string, DeckCard[]>();
  const skipped: string[] = [];
  let name = '';
  let zone: string | null = 'main';
  let sawSection = false;
  let sawCard = false;
  let afterBlank = false;

  for (const raw of text.split(/\r?\n/)) {
    const line = raw.trim();
    if (!line) {
      afterBlank = sawCard;
      continue;
    }

    const card = line.match(CARD_LINE);
    if (card) {
      // MTGO's format has no headings: the sideboard is whatever follows the first blank line.
      if (afterBlank && !sawSection && zone === 'main') zone = 'side';
      afterBlank = false;
      sawCard = true;
      const target = card[1] ? 'side' : zone;
      if (!target) continue;
      const parsed = parseCardName(card[3]);
      const count = Math.min(Number(card[2]), MAX_COUNT);
      if (!parsed.name || count < 1) {
        skipped.push(line);
        continue;
      }
      if (!zones.has(target)) zones.set(target, []);
      zones.get(target)!.push({ ...parsed, count });
      continue;
    }

    // Arena's `Name …` under `About`, the `// NAME : …` of .dec files, Forge's `Name=…`.
    const named = line.match(/^(?:\/\/\s*)?name\b\s*[:=]?\s*(.+)$/i);
    if (named && !sawCard) {
      name ||= named[1].trim();
      continue;
    }

    const heading = line.replace(/^(\/\/|#)\s*/, '').replace(/^\[(.*)\]$/, '$1').replace(/\s*:?\s*(\(\d+\)|\d+)?\s*:?$/, '').trim().toLowerCase();
    const section = SECTIONS.find(([ pattern ]) => pattern.test(heading));
    if (section) {
      zone = section[1];
      sawSection = true;
      afterBlank = false;
      continue;
    }

    // Comments, Forge's `key=value` metadata, and headings that group the deck by
    // card type (`Creatures (20)`) rather than starting another zone.
    if (/^(\/\/|#)/.test(line) || /^\w+=/.test(line) || /^\D.*\(\d+\)$/.test(line)) continue;
    skipped.push(line);
  }

  const order = [ 'main', 'side', 'tokens' ];
  return {
    deck: {
      name,
      comments: '',
      zones: order.filter((z) => zones.has(z)).map((z) => ({ name: z, cards: zones.get(z)! })),
    },
    skipped,
  };
}

/** `Lightning Bolt (M10) 146 *F* [Burn]` → the name, set and collector number. */
function parseCardName(text: string): Omit<DeckCard, 'count'> {
  let rest = text
    .replace(/\s+\*[a-z]+\*\s*$/i, '') // *F*, *E*: foil and etched
    .replace(/\s+\^[^^]*\^\s*$/, '') // Archidekt's ^colour tags^
    .replace(/\s+\[[^\]]*\]\s*$/, '') // Archidekt's [categories]
    .trim();

  // Forge: `Name|SET` or `Name|SET|art`.
  const forge = rest.match(/^(.+?)\|([a-z0-9]{2,6})(\|.*)?$/i);
  if (forge) return { name: forge[1].trim(), set: forge[2].toUpperCase() };

  const printing = rest.match(/\s+\(([a-z0-9]{2,6})\)(?:\s+(\S+))?$/i);
  if (!printing) return { name: rest };
  rest = rest.slice(0, printing.index).trim();
  return { name: rest, set: printing[1].toUpperCase(), collectorNumber: printing[2] };
}

/** The deck as a plain list most deck sites and the client's own import can read. */
export function toText(deck: DeckList): string {
  const lines = (zone?: DeckZone) => zone?.cards.map((card) => `${card.count} ${card.name}`) ?? [];
  const main = lines(deck.zones.find((zone) => zone.name === 'main'));
  const side = lines(deck.zones.find((zone) => zone.name === 'side'));
  return [ ...main, ...(side.length ? [ '', 'Sideboard', ...side ] : []), '' ].join('\n');
}

/** A `.cod` or plain-text list, whichever `content` is. */
export function parseDeck(content: string): ParsedText | null {
  if (isCod(content)) {
    const deck = parseCod(content);
    return deck ? { deck, skipped: [] } : null;
  }
  return parseText(content);
}

/** Tidies what came from outside before it's stored: a name that fits, and no empty zones. */
export function normalizeDeck(deck: DeckList, fallbackName: string): DeckList {
  const name = (deck.name.replace(/\s+/g, ' ').trim() || fallbackName).slice(0, MAX_DECK_NAME).trim();
  return { ...deck, name, comments: deck.comments.trim(), zones: deck.zones.filter((zone) => zone.cards.length) };
}
