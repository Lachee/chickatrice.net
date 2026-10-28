import { error, fail, redirect, type RequestEvent } from '@sveltejs/kit';
import { config } from '#lib/server/config.ts';
import { requireAccount } from '#lib/server/auth/guard.ts';
import { RateLimiter, addressKey } from '#lib/server/auth/ratelimit.ts';
import { logAdminAction } from '#lib/server/admin/log.ts';
import { text } from '#lib/server/admin/form.ts';
import { capabilities } from '#lib/server/cockatrice/capabilities.ts';
import { DeckError, countDecks, createDeck, deleteDeck, findDeckBySlug, setDeckVisibility, summarize, updateDeck } from '#lib/server/cockatrice/decks.ts';
import { openDeckLink, shareDeck } from '#lib/server/cockatrice/shares.ts';
import { deckAccess } from '#lib/server/decks/access.ts';
import { MAX_DECK_NAME, cardCount, deckHash, parseCod, updateCodMetadata } from '#lib/server/decks/format.ts';
import { setFlash } from '#lib/server/flash.ts';
import { cardImages, cardKey } from '#lib/server/scryfall.ts';
import { isVisibility, zoneLabel } from '#lib/decks.ts';
import type { Actions, PageServerLoad } from './$types';

const MAX_COMMENTS = 4000;

// Each one may write a deck share to Servatrice's tables.
const opens = new RateLimiter(20, 60_000);

/** The deck, if the viewer may see it. A private deck looks the same as no deck. */
async function visibleDeck(event: Pick<RequestEvent, 'params' | 'locals'>) {
	const deck = await findDeckBySlug(event.params.slug!);
	const access = deck && deckAccess(event.locals.account, deck);
	if (!deck || !access?.view) error(404, "There's no deck here, or its owner keeps it private.");
	return { deck, access };
}

export const load: PageServerLoad = async (event) => {
	const { deck, access } = await visibleDeck(event);
	const parsed = parseCod(deck.content);
	const { cover } = summarize(deck);
	const { deckShares, publicDecks } = await capabilities();

	return {
		deck: {
			slug: deck.slug,
			name: deck.name,
			ownerName: deck.ownerName,
			folder: deck.folder,
			uploadedAt: deck.uploadedAt,
			visibility: deck.visibility,
			publicFolder: deck.publicFolder,
			sourceUrl: deck.sourceUrl,
			comments: parsed?.comments ?? '',
			tags: parsed?.tags ?? [],
			zones: (parsed?.zones ?? []).map((zone) => ({
				name: zone.name,
				label: zoneLabel(zone.name),
				count: cardCount({ zones: [zone] }),
				cards: zone.cards.map((card) => ({ name: card.name, count: card.count, set: card.set ?? null, key: cardKey(card) }))
			})),
			cards: parsed ? cardCount(parsed) : null,
			hash: parsed ? deckHash(parsed) : null,
			coverKey: cover ? cardKey(cover) : null
		},
		// Streamed, so the list shows at once and the pictures follow.
		images: cardImages([...(parsed?.zones.flatMap((zone) => zone.cards) ?? []), ...(cover ? [cover] : [])]),
		access,
		canCopy: Boolean(event.locals.account) && !access.owner,
		canOpen: deckShares && parsed !== null,
		publicDecks
	};
};

/** The deck, if the signed-in account may change it. */
async function manageableDeck(event: RequestEvent) {
	const account = requireAccount(event);
	const { deck, access } = await visibleDeck(event);
	if (!access.manage) error(403, "Only the deck's owner can change it.");
	return { account, deck, access };
}

export const actions: Actions = {
	update: async (event) => {
		const { account, deck, access } = await manageableDeck(event);
		const form = await event.request.formData();
		const error = (message: string) => fail(400, { action: 'update', error: message });

		const name = text(form, 'name').replace(/\s+/g, ' ');
		const comments = String(form.get('comments') ?? '').replace(/\r\n/g, '\n').trim();
		const visibility = text(form, 'visibility');
		if (!name) return error('Give the deck a name.');
		if (name.length > MAX_DECK_NAME) return error(`Keep the name to ${MAX_DECK_NAME} characters.`);
		if (comments.length > MAX_COMMENTS) return error(`Keep the description to ${MAX_COMMENTS} characters.`);
		if (!isVisibility(visibility)) return error('Choose who can see the deck.');
		if (deck.publicFolder && visibility !== 'public')
			return error(`It's in ${deck.publicFolder}, which is public in Cockatrice. Make that folder private in the client first, or move the deck out of it.`);

		const parsed = parseCod(deck.content);
		const changed: string[] = [];
		if (parsed && (name !== deck.name || comments !== parsed.comments.trim())) {
			try {
				await updateDeck(deck, name, updateCodMetadata(deck.content, { name, comments }));
			} catch (e) {
				if (e instanceof DeckError) return error(e.message);
				throw e;
			}
			changed.push(name !== deck.name ? `renamed from “${deck.name}” to “${name}”` : 'description');
		}
		if (visibility !== deck.visibility) {
			await setDeckVisibility(deck, visibility);
			changed.push(`${deck.visibility} → ${visibility}`);
		}

		if (changed.length && !access.owner)
			await logAdminAction(account, visibility !== deck.visibility ? 'deck.visibility' : 'deck.update', deck.ownerName, `deck:${deck.id} ${deck.name}: ${changed.join(', ')}`);
		return { action: 'update', message: changed.length ? 'Saved.' : 'Nothing to change.' };
	},

	delete: async (event) => {
		const { account, deck, access } = await manageableDeck(event);
		await deleteDeck(deck);
		if (!access.owner) await logAdminAction(account, 'deck.delete', deck.ownerName, `deck:${deck.id} ${deck.name}`);
		setFlash(event.cookies, { type: 'success', message: `Deleted ${deck.name}.` });
		redirect(303, access.owner ? '/decks' : `/decks/by/${encodeURIComponent(deck.ownerName)}`);
	},

	/** A `cockatrice://opendeck` link for it, which the page follows. Anyone who can see the deck can download it anyway. */
	open: async (event) => {
		const { deck } = await visibleDeck(event);
		const error = (message: string) => fail(400, { action: 'open', error: message });
		if (!(await capabilities()).deckShares) return error("This server's Cockatrice can't open decks from links.");

		const { account } = event.locals;
		const wait = opens.hit(account ? `account:${account.id}` : addressKey(event.getClientAddress()));
		if (wait) return error(`That's a lot of decks to open. Try again in ${wait} seconds.`);

		const parsed = parseCod(deck.content);
		if (!parsed) return error("This deck can't be read, so Cockatrice couldn't open it either.");
		return { action: 'open', link: openDeckLink(await shareDeck(deck, parsed)) };
	},

	/** Into the signed-in account's own decks. Anyone who can see a deck can download it anyway. */
	copy: async (event) => {
		const account = requireAccount(event);
		const { deck } = await visibleDeck(event);
		const error = (message: string) => fail(400, { action: 'copy', error: message });

		const max = config.decks.maxPerAccount;
		if ((await countDecks(account.id)) >= max) return error(`You have ${max} decks, which is as many as an account can keep.`);

		const parsed = parseCod(deck.content);
		if (!parsed) return error("This deck can't be read, so it can't be copied.");
		let slug: string;
		try {
			slug = await createDeck(account.id, { ...parsed, name: deck.name, content: deck.content }, deck.sourceUrl);
		} catch (e) {
			if (e instanceof DeckError) return error(e.message);
			throw e;
		}
		setFlash(event.cookies, { type: 'success', message: `Copied ${deck.name} to your decks.` });
		redirect(303, `/decks/${slug}`);
	}
};
