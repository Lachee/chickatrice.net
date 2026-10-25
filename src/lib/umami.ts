import type { ActionResult } from '$app/forms';

/**
 * Custom events for Umami (https://umami.is/docs/tracker-functions), loaded by
 * `Umami.svelte`. Everything here is a no-op when it isn't configured or a
 * browser blocks it.
 *
 * Events are named `area-action` in kebab-case. Their data must never say who
 * someone is: no names, emails or deck titles.
 *
 * Call these from handlers rather than putting `data-umami-event` on links:
 * Umami stops an internal link's click and sets `location.href` itself, which
 * turns SvelteKit's navigation into a full page load.
 */

export type EventData = Record<string, string | number | boolean>;

declare global {
	interface Window {
		umami?: { track(event: string, data?: EventData): Promise<unknown> | void };
	}
}

export function track(event: string, data?: EventData): Promise<unknown> {
	if (typeof window === 'undefined') return Promise.resolve();
	if (window.umami) return Promise.resolve(window.umami.track(event, data)).catch(() => {});
	// Called while the page is still loading (a flash on the first page), before the deferred tracker has run.
	if (document.readyState !== 'complete')
		return new Promise((resolve) =>
			window.addEventListener('load', () => resolve(window.umami?.track(event, data)), { once: true })
		).catch(() => {});
	return Promise.resolve();
}

/**
 * Reports an enhanced form's outcome: `event` when the action succeeds or
 * redirects, `event-failed` when it returns a failure.
 */
export function trackResult(event: string, result: ActionResult, data?: EventData) {
	if (result.type === 'success' || result.type === 'redirect') track(event, data);
	else if (result.type === 'failure') track(`${event}-failed`, data);
}

/**
 * An `onsubmit` for forms that post away from the site (Auth.js' sign-in and
 * sign-out), which would unload the page before the event is sent. Holds the
 * submit until it has been, for half a second at most.
 */
export function trackSubmit(event: string, data?: EventData) {
	return (e: SubmitEvent) => {
		if (!window.umami) return;
		const form = e.currentTarget as HTMLFormElement;
		e.preventDefault();
		const timeout = new Promise((resolve) => setTimeout(resolve, 500));
		Promise.race([track(event, data), timeout]).then(() => form.submit());
	};
}
