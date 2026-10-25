import { error } from '@sveltejs/kit';
import { requireStaff } from '#lib/server/auth/guard.ts';
import { listAdminLog } from '#lib/server/admin/log.ts';
import { AUDIT_ACTIONS, listAudit, searchChatLog, type ChatTarget } from '#lib/server/cockatrice/logs.ts';
import type { PageServerLoad } from './$types';

const TABS = ['chat', 'audit', 'site'] as const;
const TYPES: ChatTarget[] = ['room', 'game', 'chat'];
const HOURS = [24, 24 * 7, 24 * 30, 0];

export const load: PageServerLoad = async (event) => {
	const staff = requireStaff(event, 'view');
	const { searchParams } = event.url;
	const param = searchParams.get('tab');
	const tab = TABS.find((t) => t === param) ?? 'chat';
	// Developers read chat, as Servatrice lets them; the rest is moderation data.
	if (tab !== 'chat' && !staff.access.moderate) error(403, "Your staff role doesn't allow that.");
	const get = (name: string) => (searchParams.get(name) ?? '').trim().slice(0, 255);

	if (tab === 'audit') {
		const action = AUDIT_ACTIONS.includes(get('action')) ? get('action') : '';
		return { tab, audit: { name: get('name'), action, actions: AUDIT_ACTIONS, entries: await listAudit({ name: get('name'), action }) } } as const;
	}

	if (tab === 'site') {
		const target = get('target');
		return { tab, site: { target, entries: await listAdminLog({ target: target || undefined, limit: 200 }) } } as const;
	}

	// Ticking none of the boxes reads as wanting all of them.
	const chosen = TYPES.filter((type) => searchParams.has(type));
	const hoursParam = Number(searchParams.get('hours'));
	const restricted = !staff.access.moderate;
	const search = {
		user: get('user'),
		ip: restricted ? '' : get('ip'),
		gameId: Number(get('gameId')) || undefined,
		target: get('target'),
		text: get('text'),
		types: chosen.length ? chosen : restricted ? TYPES.filter((type) => type !== 'chat') : TYPES,
		hours: HOURS.includes(hoursParam) ? hoursParam : 24 * 7,
		limit: 200,
		restricted
	};
	return { tab, chat: { search, hoursOptions: HOURS, types: TYPES, messages: await searchChatLog(search) } } as const;
};
