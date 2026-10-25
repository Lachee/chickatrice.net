/**
 * Servatrice's staff bitfield, `cockatrice_users.admin`. Admin implies
 * moderator (`evalUserQueryResult` in servatrice_database_interface.cpp).
 */
export const STAFF_FLAGS = [
	{ bit: 1, id: 'admin', label: 'Admin' },
	{ bit: 2, id: 'moderator', label: 'Moderator' },
	{ bit: 4, id: 'judge', label: 'Judge' },
	{ bit: 8, id: 'developer', label: 'Developer' }
] as const;

/** The flags Servatrice's `AdjustMod` command can change. Admin is only ever set by hand in the database. */
export const ADJUSTABLE_FLAGS = 2 | 4 | 8;

/**
 * What the admin pages let each kind of staff do, after Servatrice's command
 * families: developers get its stats commands (and the chat log without private
 * messages or addresses), moderators its moderator commands, admins the rest.
 * Judges are game-side only, so they get nothing here.
 */
export type StaffAccess = {
	/** Metrics, who is online, the chat log. */
	view: boolean;
	/** Players, bans, warnings, reports, card art rules. */
	moderate: boolean;
	/** Rooms, the server message, staff roles, privileges. */
	administer: boolean;
};

export function staffAccess(admin: number): StaffAccess {
	const administer = (admin & 1) !== 0;
	const moderate = administer || (admin & 2) !== 0;
	return { view: moderate || (admin & 8) !== 0, moderate, administer };
}

export const staffLabels = (admin: number) => STAFF_FLAGS.filter((flag) => admin & flag.bit).map((flag) => flag.label);
