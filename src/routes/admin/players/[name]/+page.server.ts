import { error, fail, type RequestEvent } from '@sveltejs/kit';
import { config } from '#lib/server/config.ts';
import { requireStaff } from '#lib/server/auth/guard.ts';
import { providerName } from '#lib/server/auth/index.ts';
import { listIdentities } from '#lib/server/auth/identities.ts';
import { deleteAccountSessions } from '#lib/server/auth/session.ts';
import { liftBanAction, placeBan, protectedFrom } from '#lib/server/admin/actions.ts';
import { checked, text } from '#lib/server/admin/form.ts';
import { listAdminLog, logAdminAction } from '#lib/server/admin/log.ts';
import { setAvatar } from '#lib/server/cockatrice/accounts.ts';
import { capabilities } from '#lib/server/cockatrice/capabilities.ts';
import {
	PRIVILEGES,
	activateAccount,
	addWarning,
	banHistory,
	findAlts,
	getPlayer,
	listPlayerSessions,
	listWarnings,
	resetPassword,
	setAdminNotes,
	setPrivilege,
	setStaffFlags,
	type Privilege
} from '#lib/server/cockatrice/moderation.ts';
import { listReports } from '#lib/server/cockatrice/reports.ts';
import { isDuplicate } from '#lib/server/cockatrice/sql.ts';
import { ADJUSTABLE_FLAGS, STAFF_FLAGS, staffLabels } from '#lib/staff.ts';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async (event) => {
	const staff = requireStaff(event, 'moderate');
	const player = await getPlayer(event.params.name);
	if (!player) error(404, `There's no account called ${event.params.name}.`);

	const caps = await capabilities();
	const [sessions, alts, bans, warnings, reports, identities, actions] = await Promise.all([
		listPlayerSessions(player.name, 25),
		findAlts(player),
		banHistory(player.name),
		listWarnings({ userId: player.id }),
		caps.reports ? listReports({ filter: 'all', userName: player.name, limit: 10 }) : Promise.resolve(null),
		listIdentities(player.id),
		listAdminLog({ target: player.name, limit: 20 })
	]);

	// The newest ban on the name is the one Servatrice goes by.
	const now = Date.now() / 1000;
	const nameBan = bans[0] && (bans[0].minutes === 0 || (bans[0].expiresAt ?? 0) > now) ? bans[0] : null;

	return {
		player,
		sessions,
		alts,
		bans,
		nameBan,
		warnings,
		reports,
		identities: identities.map((identity) => ({
			provider: providerName(identity.provider),
			username: identity.username,
			email: identity.email,
			linkedAt: Math.floor(identity.createdAt.getTime() / 1000),
			lastLoginAt: Math.floor(identity.lastLoginAt.getTime() / 1000)
		})),
		actions,
		officialWarnings: config.cockatrice.officialWarnings,
		privileges: PRIVILEGES,
		adjustableFlags: STAFF_FLAGS.filter((flag) => flag.bit & ADJUSTABLE_FLAGS),
		/** Why this staff member can't ban, warn or reset them, if they can't. */
		refusal: protectedFrom(staff, player)
	};
};

/** The player the form is about, and whether this staff member may touch them. */
async function target(event: RequestEvent<{ name: string }>, action: string, guarded = true) {
	const staff = requireStaff(event, 'moderate');
	const player = await getPlayer(event.params.name);
	if (!player) error(404, `There's no account called ${event.params.name}.`);
	const refusal = guarded ? protectedFrom(staff, player) : null;
	return { staff, player, refusal: refusal ? fail(403, { action, error: refusal }) : null };
}

export const actions: Actions = {
	ban: placeBan,
	liftBan: liftBanAction,

	warn: async (event) => {
		const { staff, player, refusal } = await target(event, 'warn');
		if (refusal) return refusal;
		const reason = text(await event.request.formData(), 'reason');
		if (!reason) return fail(400, { action: 'warn', error: 'Give the reason for the warning.' });

		try {
			await addWarning(player, staff.name, reason.slice(0, 65535));
		} catch (e) {
			if (isDuplicate(e)) return fail(400, { action: 'warn', error: 'They were warned a moment ago already.' });
			throw e;
		}
		await logAdminAction(staff, 'warn', player.name, reason);
		return { action: 'warn', message: `Warning recorded against ${player.name}.` };
	},

	activate: async (event) => {
		const { staff, player } = await target(event, 'activate', false);
		await activateAccount(player);
		await logAdminAction(staff, 'account.activate', player.name);
		return { action: 'account', message: `${player.name} is activated and can log in.` };
	},

	resetPassword: async (event) => {
		const { staff, player, refusal } = await target(event, 'account');
		if (refusal) return refusal;
		const password = await resetPassword(player.id);
		// Whoever was signed in here with the old one shouldn't stay signed in.
		await deleteAccountSessions(player.id);
		await logAdminAction(staff, 'account.password', player.name, 'reset to a temporary password');
		return {
			action: 'account',
			message: (await capabilities()).forcePasswordChange
				? `Give ${player.name} this password. Cockatrice makes them choose a new one when they first log in with it.`
				: `Give ${player.name} this password, and ask them to change it.`,
			password
		};
	},

	removeAvatar: async (event) => {
		const { staff, player, refusal } = await target(event, 'account');
		if (refusal) return refusal;
		await setAvatar(player.id, Buffer.alloc(0));
		await logAdminAction(staff, 'account.avatar', player.name, 'removed');
		return { action: 'account', message: 'Avatar removed. Players see the change once they reconnect.' };
	},

	signOut: async (event) => {
		const { staff, player, refusal } = await target(event, 'account');
		if (refusal) return refusal;
		await deleteAccountSessions(player.id);
		await logAdminAction(staff, 'account.signout', player.name);
		return { action: 'account', message: `${player.name} is signed out of this website everywhere.` };
	},

	notes: async (event) => {
		const { staff, player } = await target(event, 'notes', false);
		if (!(await capabilities()).adminNotes) return fail(400, { action: 'notes', error: "This server's database has no notes column." });
		const notes = String((await event.request.formData()).get('notes') ?? '').slice(0, 65535);
		await setAdminNotes(player.id, notes);
		await logAdminAction(staff, 'account.notes', player.name, 'updated');
		return { action: 'notes', message: 'Notes saved.' };
	},

	staff: async (event) => {
		const { staff, player, refusal } = await target(event, 'staff');
		if (!staff.access.administer) return fail(403, { action: 'staff', error: 'Only an admin can change staff roles.' });
		if (refusal) return refusal;
		const form = await event.request.formData();
		const flags = STAFF_FLAGS.filter((flag) => flag.bit & ADJUSTABLE_FLAGS && checked(form, flag.id)).reduce((n, flag) => n | flag.bit, 0);

		await setStaffFlags(player.id, flags);
		const after = (player.admin & ~ADJUSTABLE_FLAGS) | flags;
		const roles = staffLabels(after).join(', ') || 'none';
		await logAdminAction(staff, 'account.staff', player.name, roles);
		const demoted = (player.admin & ~after) !== 0;
		return {
			action: 'staff',
			message: `${player.name}'s roles: ${roles}.${demoted ? ' Servatrice only notices when they next log in, so if they are connected, they keep the old roles until then.' : ''}`
		};
	},

	privilege: async (event) => {
		const { staff, player } = await target(event, 'privilege', false);
		if (!staff.access.administer) return fail(403, { action: 'privilege', error: 'Only an admin can change privileges.' });
		const form = await event.request.formData();
		const level = text(form, 'level') as Privilege;
		if (!PRIVILEGES.includes(level)) return fail(400, { action: 'privilege', error: 'Choose a privilege level.' });
		const until = text(form, 'until');
		const endsAt = until ? new Date(`${until}T00:00:00Z`) : null;
		if (endsAt && Number.isNaN(endsAt.getTime())) return fail(400, { action: 'privilege', error: "That date isn't valid." });

		await setPrivilege(player.id, level, endsAt);
		await logAdminAction(staff, 'account.privilege', player.name, `${level}${until ? ` until ${until}` : ''}`);
		return { action: 'privilege', message: `Privilege set to ${level === 'NONE' ? 'none' : level}.` };
	}
};
