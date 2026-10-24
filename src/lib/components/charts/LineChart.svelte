<script lang="ts">
	import { onMount } from 'svelte';
	import { compact, full, niceTicks } from '#lib/charts.js';

	type Point = { t: number; v: number };

	let {
		points,
		label,
		height = 200,
		/** How x positions read: clock times for short ranges, dates for long ones. */
		timeFormat = 'time'
	}: {
		points: Point[];
		/** The measure, for the tooltip and the table: "Players online". */
		label: string;
		height?: number;
		timeFormat?: 'time' | 'date';
	} = $props();

	const PAD = { top: 12, right: 12, bottom: 26, left: 40 };

	let width = $state(640);
	// Times are formatted in the reader's own zone, which the server can't know,
	// so the axis labels only appear once the page is running in the browser.
	let mounted = $state(false);
	onMount(() => (mounted = true));

	const max = $derived(Math.max(0, ...points.map((p) => p.v)));
	// Headroom above the peak, so its label always fits over it.
	const ticks = $derived(niceTicks(max * 1.15));
	const top = $derived(ticks[ticks.length - 1]);
	const plotW = $derived(Math.max(1, width - PAD.left - PAD.right));
	const plotH = $derived(height - PAD.top - PAD.bottom);

	const x = (i: number) => PAD.left + (points.length > 1 ? (i / (points.length - 1)) * plotW : plotW / 2);
	const y = (v: number) => PAD.top + plotH - (v / top) * plotH;

	const line = $derived(points.map((p, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(p.v).toFixed(1)}`).join(''));
	const area = $derived(
		points.length ? `${line}L${x(points.length - 1).toFixed(1)},${y(0)}L${x(0).toFixed(1)},${y(0)}Z` : ''
	);

	const formatter = $derived(
		new Intl.DateTimeFormat(undefined, timeFormat === 'time' ? { hour: 'numeric', minute: '2-digit' } : { month: 'short', day: 'numeric' })
	);
	const fullFormatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });
	const formatT = (t: number) => formatter.format(new Date(t * 1000));

	/** About one label per 110px, spread evenly, ends included. */
	const xTicks = $derived.by(() => {
		if (!mounted || points.length < 2) return [];
		const count = Math.max(2, Math.min(6, Math.floor(plotW / 110)));
		return Array.from({ length: count }, (_, k) => Math.round((k / (count - 1)) * (points.length - 1)));
	});

	let active = $state<number | null>(null);

	function onpointermove(event: PointerEvent) {
		const svg = event.currentTarget as SVGSVGElement;
		const rect = svg.getBoundingClientRect();
		const ratio = (event.clientX - rect.left - PAD.left) / plotW;
		active = Math.max(0, Math.min(points.length - 1, Math.round(ratio * (points.length - 1))));
	}

	function onkeydown(event: KeyboardEvent) {
		if (!points.length) return;
		if (event.key === 'ArrowLeft') active = Math.max(0, (active ?? points.length) - 1);
		else if (event.key === 'ArrowRight') active = Math.min(points.length - 1, (active ?? -1) + 1);
		else if (event.key === 'Escape') active = null;
		else return;
		event.preventDefault();
	}

	const peak = $derived(points.reduce((best, p, i) => (p.v > (points[best]?.v ?? -1) ? i : best), 0));
</script>

<div class="relative" bind:clientWidth={width}>
	<!-- Focusable so the arrow keys can step through values, as the pointer does. -->
	<!-- svelte-ignore a11y_no_noninteractive_tabindex, a11y_no_noninteractive_element_interactions -->
	<svg
		{width}
		{height}
		role="application"
		aria-roledescription="chart"
		tabindex="0"
		aria-label="{label}: peak {full(max)}. Use the arrow keys to read values."
		class="block touch-pan-y outline-none focus-visible:ring-3 focus-visible:ring-ring/50 rounded-md"
		{onpointermove}
		onpointerleave={() => (active = null)}
		onblur={() => (active = null)}
		{onkeydown}
	>
		{#each ticks as tick (tick)}
			<line x1={PAD.left} x2={width - PAD.right} y1={y(tick)} y2={y(tick)} class="stroke-chart-grid" stroke-width="1" />
			<text x={PAD.left - 8} y={y(tick)} dy="0.32em" text-anchor="end" class="fill-muted-foreground text-[11px] tabular-nums">{compact(tick)}</text>
		{/each}

		{#each xTicks as i, k (i)}
			<text
				x={x(i)}
				y={height - 8}
				text-anchor={k === 0 ? 'start' : k === xTicks.length - 1 ? 'end' : 'middle'}
				class="fill-muted-foreground text-[11px] tabular-nums"
			>{formatT(points[i].t)}</text>
		{/each}

		{#if points.length}
			<path d={area} class="fill-chart-1" fill-opacity="0.1" />
			<path d={line} class="stroke-chart-1" fill="none" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

			<!-- The peak is the one value worth labelling on the chart itself. -->
			{#if max > 0 && active === null}
				<circle cx={x(peak)} cy={y(max)} r="4" class="fill-chart-1 stroke-card" stroke-width="2" />
			{/if}
		{/if}

		{#if active !== null && points[active]}
			<line x1={x(active)} x2={x(active)} y1={PAD.top} y2={PAD.top + plotH} class="stroke-muted-foreground/50" stroke-width="1" />
			<circle cx={x(active)} cy={y(points[active].v)} r="4" class="fill-chart-1 stroke-card" stroke-width="2" />
		{/if}
	</svg>

	{#if active !== null && points[active]}
		{@const left = x(active)}
		<div
			class="pointer-events-none absolute top-1 z-10 rounded-md border bg-popover px-2.5 py-1.5 text-xs shadow-lg whitespace-nowrap"
			style="left: {left}px; transform: translateX({left > width / 2 ? 'calc(-100% - 10px)' : '10px'})"
		>
			<div class="text-sm font-semibold tabular-nums">{full(points[active].v)}</div>
			<div class="flex items-center gap-1.5 text-muted-foreground">
				<span class="inline-block h-0.5 w-3 rounded bg-chart-1"></span>{label}
			</div>
			<div class="text-muted-foreground">{fullFormatter.format(new Date(points[active].t * 1000))}</div>
		</div>
	{/if}

	{#if max > 0 && active === null}
		{@const px = x(peak)}
		{@const shift = px < PAD.left + 20 ? '0' : px > width - 30 ? '-100%' : '-50%'}
		{@const py = y(max) - 20}
		<div class="pointer-events-none absolute text-[11px] font-medium tabular-nums text-foreground" style="left: {px}px; top: {py}px; transform: translateX({shift})">
			{full(max)}
		</div>
	{/if}
</div>

<details class="mt-2 text-xs text-muted-foreground">
	<summary class="cursor-pointer select-none hover:text-foreground">Show as table</summary>
	<div class="mt-2 max-h-56 overflow-auto rounded-md border">
		<table class="w-full tabular-nums">
			<thead class="sticky top-0 bg-card">
				<tr><th class="px-2 py-1 text-left font-medium">Time</th><th class="px-2 py-1 text-right font-medium">{label}</th></tr>
			</thead>
			<tbody>
				{#each points as point (point.t)}
					<tr class="border-t">
						<td class="px-2 py-0.5">{mounted ? fullFormatter.format(new Date(point.t * 1000)) : new Date(point.t * 1000).toISOString().slice(0, 16).replace('T', ' ') + ' UTC'}</td>
						<td class="px-2 py-0.5 text-right text-foreground">{full(point.v)}</td>
					</tr>
				{/each}
			</tbody>
		</table>
	</div>
</details>
