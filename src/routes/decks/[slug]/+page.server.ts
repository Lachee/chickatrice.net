import { error, fail, redirect, type RequestEvent } from '@sveltejs/kit';
import { config } from '#lib/server/config.ts';
import { requireAccount } from '#lib/server/auth/guard.ts';
import { logAdminAction } from '#lib/server/admin/log.ts';
import { text } from '#lib/server/admin/form.ts';
import { capabilities } from '#lib/server/cockatrice/capabilities.ts';
import { DeckError, countDecks, createDeck, deleteDeck, findDeckBySlug, setDeckVisibility, updateDeck } from '#lib/server/cockatrice/decks.ts';
import { deckAccess } from '#lib/server/decks/access.ts';
import { MAX_DECK_NAME, cardCount, deckHash, parseCod, updateCodMetadata } from '#lib/server/decks/format.ts';
import { setFlash } from '#lib/server/flash.ts';
import { isVisibility, zoneLabel } from '#lib/decks.ts';
import type { Actions, PageServerLoad } from './$types';

const MAX_COMMENTS = 4000;

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
				cards: zone.cards.map(({ name, count, set }) => ({ name, count, set: set ?? null }))
			})),
			cards: parsed ? cardCount(parsed) : null,
			hash: parsed ? deckHash(parsed) : null
		},
		access,
		canCopy: Boolean(event.locals.account) && !access.owner,
		publicDecks: (await capabilities()).publicDecks
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
