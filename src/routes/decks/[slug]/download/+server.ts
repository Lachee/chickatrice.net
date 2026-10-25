import { error } from '@sveltejs/kit';
import { findDeckBySlug } from '#lib/server/cockatrice/decks.ts';
import { deckAccess } from '#lib/server/decks/access.ts';
import { parseCod, toText } from '#lib/server/decks/format.ts';
import type { RequestHandler } from './$types';

/** `?format=txt` for a plain list; otherwise the `.cod` Cockatrice opens. */
export const GET: RequestHandler = async ({ params, url, locals }) => {
	const deck = await findDeckBySlug(params.slug);
	if (!deck || !deckAccess(locals.account, deck).view) error(404, "There's no deck here, or its owner keeps it private.");

	const asText = url.searchParams.get('format') === 'txt';
	let body = deck.content;
	if (asText) {
		const parsed = parseCod(deck.content);
		if (!parsed) error(422, "This deck can't be read, so it can only be downloaded as it is.");
		body = toText(parsed);
	}

	// Characters Windows refuses in file names go; the rest are spelled out for browsers that read filename*.
	const base = deck.name.replace(/[\\/:*?"<>|\u0000-\u001f]+/g, ' ').trim() || 'deck';
	const filename = `${base}.${asText ? 'txt' : 'cod'}`;
	return new Response(body, {
		headers: {
			'Content-Type': asText ? 'text/plain; charset=utf-8' : 'application/xml; charset=utf-8',
			'Content-Disposition': `attachment; filename="${filename.replace(/[^\x20-\x7e]/g, '_')}"; filename*=UTF-8''${encodeURIComponent(filename)}`,
			'Cache-Control': 'private, no-store',
			'X-Robots-Tag': 'noindex'
		}
	});
};
