import { error, fail, type RequestEvent } from '@sveltejs/kit';
import { requireStaff } from '#lib/server/auth/guard.ts';
import { text } from '#lib/server/admin/form.ts';
import { logAdminAction } from '#lib/server/admin/log.ts';
import { capabilities } from '#lib/server/cockatrice/capabilities.ts';
import { REPORT_CATEGORIES, addReportComment, assignReport, getReport, resolveReport } from '#lib/server/cockatrice/reports.ts';
import type { Actions, PageServerLoad } from './$types';

async function report(event: RequestEvent<{ id: string }>) {
	const staff = requireStaff(event, 'moderate');
	if (!(await capabilities()).reports) error(404, "This server's database has no reports.");
	const id = Number(event.params.id);
	const found = Number.isInteger(id) && id > 0 ? await getReport(id) : null;
	if (!found) error(404, `There's no report #${event.params.id}.`);
	return { staff, report: found };
}

export const load: PageServerLoad = async (event) => {
	const { report: found } = await report(event);
	return { report: found, category: REPORT_CATEGORIES[found.category.toLowerCase()] ?? found.category };
};

export const actions: Actions = {
	assign: async (event) => {
		const { staff, report: found } = await report(event);
		if (!(await assignReport(found.id, staff.id))) return fail(409, { action: 'assign', error: 'Someone else has taken it, or it is closed.' });
		await logAdminAction(staff, 'report.assign', `report:${found.id}`);
		return { action: 'assign', message: "It's yours." };
	},

	resolve: async (event) => {
		const { staff, report: found } = await report(event);
		const form = await event.request.formData();
		const dismissed = form.get('outcome') === 'dismiss';
		const note = text(form, 'note').slice(0, 65535);

		if (!(await resolveReport(found.id, staff.id, note, dismissed)))
			return fail(409, { action: 'resolve', error: 'It has been closed already.' });
		await logAdminAction(staff, dismissed ? 'report.dismiss' : 'report.resolve', `report:${found.id}`, note || null);
		return {
			action: 'resolve',
			message: `${dismissed ? 'Dismissed' : 'Resolved'}. ${found.reporterName} is told next time they log in.`
		};
	},

	comment: async (event) => {
		const { staff, report: found } = await report(event);
		const comment = text(await event.request.formData(), 'comment');
		if (!comment) return fail(400, { action: 'comment', error: 'Write something first.' });

		if (!(await addReportComment(found.id, staff, comment.slice(0, 65535))))
			return fail(409, { action: 'comment', error: "It's closed, so it can't take comments." });
		await logAdminAction(staff, 'report.comment', `report:${found.id}`);
		return { action: 'comment', message: 'Comment added. The reporter sees it next time they log in.' };
	}
};
