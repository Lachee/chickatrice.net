/** Clean y-axis ticks from 0 to at least `max`: 0, 5, 10, 15 rather than 0, 4.7, 9.4. */
export function niceTicks(max: number, count = 4): number[] {
	if (max <= 0) return [0, 1];
	const raw = max / count;
	const magnitude = 10 ** Math.floor(Math.log10(raw));
	const step = [1, 2, 2.5, 5, 10].map((m) => m * magnitude).find((s) => s >= raw) ?? raw;
	// Whole things only: there's no such thing as half a player.
	const whole = Math.max(1, Math.ceil(step));
	const ticks: number[] = [];
	for (let value = 0; value < max + whole; value += whole) ticks.push(value);
	return ticks;
}

const compactFormat = new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 });
const fullFormat = new Intl.NumberFormat('en');

/** 1,284 → 1.3K; below 1,000 numbers are shown in full. */
export const compact = (value: number) => (value < 1000 ? fullFormat.format(value) : compactFormat.format(value));
export const full = (value: number) => fullFormat.format(value);

/** 93784 → "1d 2h"; 3725 → "1h 2m"; 42 → "42s". */
export function duration(seconds: number): string {
	const d = Math.floor(seconds / 86400);
	const h = Math.floor((seconds % 86400) / 3600);
	const m = Math.floor((seconds % 3600) / 60);
	if (d) return `${d}d ${h}h`;
	if (h) return `${h}h ${m}m`;
	if (m) return `${m}m`;
	return `${Math.floor(seconds)}s`;
}
