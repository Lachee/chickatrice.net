import type { Account } from '#lib/server/cockatrice/accounts.ts';
import type { Deck } from '#lib/server/cockatrice/decks.ts';
import { staffAccess } from '#lib/staff.ts';

export type DeckAccess = {
  /** Open it, download it, copy it. */
  view: boolean;
  /** Rename it, change who can see it, delete it. */
  manage: boolean;
  owner: boolean;
};

/** What `viewer` may do with `deck`: its owner and the server's admins anything, others only look if it's shared. */
export function deckAccess(viewer: Pick<Account, 'id' | 'admin'> | null, deck: Pick<Deck, 'ownerId' | 'visibility'>): DeckAccess {
  const owner = viewer?.id === deck.ownerId;
  const manage = owner || isDeckAdmin(viewer);
  return { view: manage || deck.visibility !== 'private', manage, owner };
}

/** Admins (not moderators) look after everyone's decks, private ones included. */
export const isDeckAdmin = (viewer: Pick<Account, 'admin'> | null) => Boolean(viewer && staffAccess(viewer.admin).administer);
