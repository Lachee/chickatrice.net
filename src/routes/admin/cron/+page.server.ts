import { error, fail } from '@sveltejs/kit';
import { requireStaff } from '#lib/server/auth/guard.ts';
import { logAdminAction } from '#lib/server/admin/log.ts';
import { text } from '#lib/server/admin/form.ts';
import { getJobStatuses, runJob } from '#lib/server/cron/index.ts';
import type { Actions, PageServerLoad } from './$types';

export const load: PageServerLoad = async (event) => {
	requireStaff(event, 'view');
	return { jobs: await getJobStatuses() };
};

export const actions: Actions = {
	run: async (event) => {
		const staff = requireStaff(event, 'administer');
		const name = text(await event.request.formData(), 'name');
		const outcome = await runJob(name);
		if (!outcome) error(404, 'No such job.');
		if (!outcome.ran) return fail(409, { action: 'run', name, error: `${name} is already running, here or on another instance; skipped.` });

		await logAdminAction(staff, 'cron.run', `cron:${name}`, outcome.error ? `failed: ${outcome.error}` : (outcome.summary ?? null));
		if (outcome.error) return fail(500, { action: 'run', name, error: `${name} failed: ${outcome.error}` });
		return { action: 'run', name, message: outcome.summary ? `${name} finished: ${outcome.summary}.` : `${name} finished, with nothing to do.` };
	}
};
