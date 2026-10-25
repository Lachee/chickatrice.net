import { error } from '@sveltejs/kit';
import { requireStaff } from '#lib/server/auth/guard.ts';
import { capabilities } from '#lib/server/cockatrice/capabilities.ts';
import { REPORT_CATEGORIES, REPORT_STATUSES, getReportStats, listReports, type ReportFilter } from '#lib/server/cockatrice/reports.ts';
import type { PageServerLoad } from './$types';

const LIMIT = 50;
const FILTERS: ReportFilter[] = ['unresolved', 'mine', ...REPORT_STATUSES, 'all'];

export const load: PageServerLoad = async (event) => {
	const staff = requireStaff(event, 'moderate');
	if (!(await capabilities()).reports) error(404, "This server's database has no reports: they arrived with Servatrice's schema 36.");

	const { searchParams } = event.url;
	const param = searchParams.get('filter') as ReportFilter | null;
	const filter = param && FILTERS.includes(param) ? param : 'unresolved';
	const offset = Math.max(0, Number(searchParams.get('offset')) || 0);

	const [list, stats] = await Promise.all([listReports({ filter, staffId: staff.id, limit: LIMIT, offset }), getReportStats()]);
	return { filter, filters: FILTERS, offset, limit: LIMIT, ...list, stats, categories: REPORT_CATEGORIES };
};
