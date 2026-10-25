/** A one-off message shown on the next page; see `#lib/server/flash`. */
export type Flash = {
	type: 'success' | 'error' | 'info';
	message: string;
	/** An Umami event the page reports with it, for outcomes only the server sees (`#lib/umami.ts`). */
	event?: { name: string; data?: Record<string, string> };
};
