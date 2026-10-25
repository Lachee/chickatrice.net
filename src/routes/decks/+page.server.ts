import { fail, redirect } from '@sveltejs/kit';
import { config } from '#lib/server/config.ts';
import { requireAccount } from '#lib/server/auth/guard.ts';
import { RateLimiter } from '#lib/server/auth/ratelimit.ts';
import { capabilities } from '#lib/server/cockatrice/capabilities.ts';
import { DeckError, countDecks, createDeck, listDecks, summarize } from '#lib/server/cockatrice/decks.ts';
import { cardCount, isCod, normalizeDeck, parseDeck, updateCodMetadata, writeCod } from '#lib/server/decks/format.ts';
import { ImportError, importFromUrl, importerNames } from '#lib/server/decks/import.ts';
import { text } from '#lib/server/admin/form.ts';
import { setFlash } from '#lib/server/flash.ts';
import type { Actions, PageServerLoad } from './$types';

/** Bigger than any deck list, small enough not to matter. */
const MAX_UPLOAD_BYTES = 256 * 1024;

// Each import is a request to someone else's site.
const imports = new RateLimiter(10, 60_000);

export const load: PageServerLoad = async (event) => {
	const account = requireAccount(event);
	const decks = await listDecks(account.id);
	return {
		decks: decks.map(summarize),
		maxDecks: config.decks.maxPerAccount,
		importers: importerNames,
		publicDecks: (await capabilities()).publicDecks
	};
};

/** Why `accountId` can't add another deck, or null. */
async function full(accountId: number): Promise<string | null> {
	const max = config.decks.maxPerAccount;
	return (await countDecks(accountId)) >= max ? `You have ${max} decks, which is as many as an account can keep. Delete some first.` : null;
}

/** Notes what an upload left out, for the message after it. */
const skippedNote = (skipped: string[]) =>
	skipped.length ? ` ${skipped.length} line${skipped.length === 1 ? " wasn't" : "s weren't"} understood and left out: “${skipped.slice(0, 3).join('”, “')}”${skipped.length > 3 ? '…' : ''}` : '';

export const actions: Actions = {
	upload: async (event) => {
		const account = requireAccount(event);
		const form = await event.request.formData();
		const error = (message: string) => fail(400, { action: 'upload', error: message });

		const refusal = await full(account.id);
		if (refusal) return error(refusal);

		const file = form.get('file');
		const pasted = text(form, 'list');
		let content: string;
		let fallbackName = 'Uploaded deck';
		if (file instanceof File && file.size > 0) {
			if (file.size > MAX_UPLOAD_BYTES) return error("That file is too big to be a deck list.");
			content = await file.text();
			fallbackName = file.name.replace(/\.[^.]*$/, '') || fallbackName;
		} else if (pasted) {
			if (pasted.length > MAX_UPLOAD_BYTES) return error("That's too long to be a deck list.");
			content = pasted;
		} else {
			return error('Choose a deck file, or paste a list.');
		}

		const parsed = parseDeck(content);
		if (!parsed || cardCount(parsed.deck) === 0)
			return error("Couldn't find any cards in that. Lines should look like “4 Lightning Bolt”.");

		const deck = normalizeDeck({ ...parsed.deck, name: text(form, 'name') || parsed.deck.name }, fallbackName);

		// A .cod is kept as it came, so whatever the client put in it survives.
		const cod = isCod(content)
			? (deck.name === parsed.deck.name ? content : updateCodMetadata(content, { name: deck.name, comments: parsed.deck.comments }))
			: writeCod(deck);

		let slug: string;
		try {
			slug = await createDeck(account.id, { ...deck, content: cod });
		} catch (e) {
			if (e instanceof DeckError) return error(e.message);
			throw e;
		}
		setFlash(event.cookies, { type: 'success', message: `Added ${deck.name}.${skippedNote(parsed.skipped)}` });
		redirect(303, `/decks/${slug}`);
	},

	import: async (event) => {
		const account = requireAccount(event);
		const form = await event.request.formData();
		const error = (message: string) => fail(400, { action: 'import', error: message, url: text(form, 'url') });

		const refusal = await full(account.id);
		if (refusal) return error(refusal);
		const url = text(form, 'url');
		if (!url) return error('Paste a link to the deck.');
		const wait = imports.hit(`account:${account.id}`);
		if (wait) return error(`That's a lot of imports. Try again in ${wait} seconds.`);

		let slug: string;
		let name: string;
		try {
			const imported = await importFromUrl(url);
			const deck = normalizeDeck(imported.deck, 'Imported deck');
			if (cardCount(deck) === 0) return error('That deck has no cards in it.');
			slug = await createDeck(account.id, { ...deck, content: writeCod(deck) }, imported.sourceUrl);
			name = deck.name;
		} catch (e) {
			if (e instanceof ImportError || e instanceof DeckError) return error(e.message);
			throw e;
		}
		setFlash(event.cookies, { type: 'success', message: `Imported ${name}.` });
		redirect(303, `/decks/${slug}`);
	}
};
