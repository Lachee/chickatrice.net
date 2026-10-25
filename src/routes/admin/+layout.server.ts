import { requireStaff } from '#lib/server/auth/guard.ts';
import { capabilities } from '#lib/server/cockatrice/capabilities.ts';
import { getModerationCounts } from '#lib/server/cockatrice/metrics.ts';
import { staffLabels } from '#lib/staff.ts';
import type { LayoutServerLoad } from './$types';

export const load: LayoutServerLoad = async (event) => {
	const staff = requireStaff(event, 'view');
	const caps = await capabilities();
	return {
		staff: { id: staff.id, name: staff.name, roles: staffLabels(staff.admin), ...staff.access },
		caps: { reports: caps.reports, cardArtRules: caps.cardArtRules, adminNotes: caps.adminNotes },
		openReports: staff.access.moderate && caps.reports ? (await getModerationCounts()).openReports : null
	};
};
