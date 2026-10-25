/**
 * Who can see a deck:
 * - `private`: its owner, and the server's admins.
 * - `unlisted`: also anyone with its link.
 * - `public`: also listed on the owner's decks page, and (on Servatrice schema
 *   37+) shown to other players in the client.
 */
export type DeckVisibility = 'private' | 'unlisted' | 'public';

export const VISIBILITIES: { id: DeckVisibility; label: string; description: string }[] = [
	{ id: 'private', label: 'Private', description: 'Only its owner.' },
	{ id: 'unlisted', label: 'Unlisted', description: 'Anyone with the link.' },
	{ id: 'public', label: 'Public', description: "Anyone; it's listed on its owner's decks page." }
];

export const isVisibility = (value: unknown): value is DeckVisibility => VISIBILITIES.some((v) => v.id === value);

/** Cockatrice's zone names, as people know them. */
export const zoneLabel = (zone: string) => ({ main: 'Main deck', side: 'Sideboard', tokens: 'Tokens' })[zone] ?? zone;
