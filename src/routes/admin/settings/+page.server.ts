import { fail } from '@sveltejs/kit';
import { config } from '#lib/server/config.ts';
import { requireStaff } from '#lib/server/auth/guard.ts';
import { checked, integer, text } from '#lib/server/admin/form.ts';
import { logAdminAction } from '#lib/server/admin/log.ts';
import { capabilities } from '#lib/server/cockatrice/capabilities.ts';
import { isDuplicate } from '#lib/server/cockatrice/sql.ts';
import {
	CARD_ART_LIMIT,
	ROOM_LIMITS,
	ROOM_PERMISSIONS,
	ROOM_PRIVILEGES,
	deleteRoom,
	listCardArtRules,
	listRooms,
	listServerMessages,
	postServerMessage,
	removeCardArtRule,
	saveRoom,
	setCardArtRule,
	type RoomInput
} from '#lib/server/cockatrice/settings.ts';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async (event) => {
	requireStaff(event, 'moderate');
	const [rooms, messages, cardArt] = await Promise.all([listRooms(), listServerMessages(10), listCardArtRules()]);
	return {
		rooms,
		messages,
		cardArt,
		serverId: config.cockatrice.serverId,
		permissions: ROOM_PERMISSIONS,
		privileges: ROOM_PRIVILEGES,
		limits: ROOM_LIMITS
	};
};

/** A room from its form, or why it can't be one. */
function readRoom(form: FormData): RoomInput | string {
	const name = text(form, 'name');
	const description = text(form, 'description');
	const joinMessage = text(form, 'joinMessage');
	const permissionLevel = text(form, 'permissionLevel') as RoomInput['permissionLevel'];
	const privilegeLevel = text(form, 'privilegeLevel') as RoomInput['privilegeLevel'];
	const chatHistorySize = integer(form, 'chatHistorySize');
	// One per line; the same one twice would show twice in the client.
	const gameTypes = [...new Set(text(form, 'gameTypes').split(/\r?\n/).map((line) => line.trim()).filter(Boolean))];

	if (!name) return 'Give the room a name.';
	if (name.length > ROOM_LIMITS.name) return `Room names are at most ${ROOM_LIMITS.name} characters.`;
	if (description.length > ROOM_LIMITS.description) return `The description is at most ${ROOM_LIMITS.description} characters.`;
	if (joinMessage.length > ROOM_LIMITS.joinMessage) return `The join message is at most ${ROOM_LIMITS.joinMessage} characters.`;
	if (!ROOM_PERMISSIONS.includes(permissionLevel)) return 'Choose who may join.';
	if (!ROOM_PRIVILEGES.includes(privilegeLevel)) return 'Choose a privilege level.';
	if (chatHistorySize === null || chatHistorySize < 0 || chatHistorySize > ROOM_LIMITS.chatHistorySize)
		return `Chat history is 0 to ${ROOM_LIMITS.chatHistorySize} messages.`;
	const long = gameTypes.find((type) => type.length > ROOM_LIMITS.gameType);
	if (long) return `“${long}” is longer than ${ROOM_LIMITS.gameType} characters.`;

	return { name, description, joinMessage, permissionLevel, privilegeLevel, autoJoin: checked(form, 'autoJoin'), chatHistorySize, gameTypes };
}

export const actions: Actions = {
	saveRoom: async (event) => {
		const staff = requireStaff(event, 'administer');
		const form = await event.request.formData();
		const id = integer(form, 'id');
		const room = readRoom(form);
		if (typeof room === 'string') return fail(400, { action: 'room', id, error: room });

		const saved = await saveRoom(id, room);
		await logAdminAction(staff, id === null ? 'room.create' : 'room.update', `room:${saved}`, `${room.name}: ${room.gameTypes.join(', ')}`);
		return { action: 'room', id: saved, message: `${room.name} saved. Restart Servatrice for players to see it.` };
	},

	deleteRoom: async (event) => {
		const staff = requireStaff(event, 'administer');
		const form = await event.request.formData();
		const id = integer(form, 'id');
		const room = (await listRooms()).find((r) => r.id === id);
		if (!room) return fail(404, { action: 'room', id, error: 'That room is gone already.' });
		if (text(form, 'confirm') !== room.name) return fail(400, { action: 'room', id, error: `Type ${room.name} to confirm.` });

		await deleteRoom(room.id);
		await logAdminAction(staff, 'room.delete', `room:${room.id}`, room.name);
		return { action: 'room', id: null, message: `${room.name} deleted. It goes from the server when Servatrice restarts.` };
	},

	postMessage: async (event) => {
		const staff = requireStaff(event, 'administer');
		const message = String((await event.request.formData()).get('message') ?? '').trim();
		if (!message) return fail(400, { action: 'message', error: 'Write the message first.' });
		if (message.length > 65535) return fail(400, { action: 'message', error: 'That message is too long.' });

		try {
			await postServerMessage(message);
		} catch (e) {
			if (isDuplicate(e)) return fail(400, { action: 'message', error: 'A message was posted this second already; try again.' });
			throw e;
		}
		await logAdminAction(staff, 'servermessage', null, message.slice(0, 500));
		return {
			action: 'message',
			message: 'Posted. Servatrice shows it once it restarts, or when an admin uses Server → Update server message in the client.'
		};
	},

	setCardArt: async (event) => {
		const staff = requireStaff(event, 'moderate');
		if (!(await capabilities()).cardArtRules) return fail(400, { action: 'cardArt', error: "This server's database has no card art rules." });
		const form = await event.request.formData();
		const cardName = text(form, 'cardName');
		const providerId = text(form, 'providerId');
		const mode = text(form, 'mode');
		const reason = text(form, 'reason');
		if (!cardName) return fail(400, { action: 'cardArt', error: 'Which card?' });
		if (mode !== 'ALLOW' && mode !== 'DENY') return fail(400, { action: 'cardArt', error: 'Allow it or deny it?' });
		if ([cardName, providerId, reason].some((value) => value.length > CARD_ART_LIMIT))
			return fail(400, { action: 'cardArt', error: `Keep each field under ${CARD_ART_LIMIT} characters.` });

		await setCardArtRule({ cardName, providerId, mode, reason }, staff.id);
		await logAdminAction(staff, 'cardart.set', `card:${cardName}`, `${mode}${providerId ? ` (${providerId})` : ''}${reason ? `: ${reason}` : ''}`);
		return { action: 'cardArt', message: `${cardName} is ${mode === 'DENY' ? 'denied' : 'allowed'} as profile art.` };
	},

	removeCardArt: async (event) => {
		const staff = requireStaff(event, 'moderate');
		const id = integer(await event.request.formData(), 'id');
		const removed = id === null ? null : await removeCardArtRule(id);
		if (!removed) return fail(404, { action: 'cardArt', error: 'That rule is gone already.' });
		await logAdminAction(staff, 'cardart.remove', `card:${removed.cardName}`, removed.providerId || null);
		return { action: 'cardArt', message: `Rule for ${removed.cardName} removed.` };
	}
};
