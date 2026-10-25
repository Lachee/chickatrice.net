import { createHash } from 'node:crypto';
import { error } from '@sveltejs/kit';
import { getAvatar } from '#lib/server/cockatrice/accounts.ts';
import { sniffImageType } from '#lib/server/avatar.ts';
import type { RequestHandler } from './$types';

/** A player's in-game avatar. Public, as it is to anyone connected to the server.  */
export const GET: RequestHandler = async ({ params, request }) => {
	const avatar = await getAvatar(params.name);
	if (!avatar)
		error(404, 'No avatar');

	const etag = `"${createHash('sha1').update(avatar).digest('base64url')}"`;
	const headers = {
		'content-type': sniffImageType(avatar),
		'cache-control': 'public, no-cache',
		etag,
		'x-content-type-options': 'nosniff',
		'content-security-policy': "default-src 'none'"
	};

	if (request.headers.get('if-none-match') === etag)
		return new Response(null, { status: 304, headers });

	return new Response(new Uint8Array(avatar), { headers });
};
