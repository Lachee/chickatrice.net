<script lang="ts">
	import { compact, full, niceTicks } from '#lib/charts.js';

	type Column = { day: string; v: number };

	let {
		columns,
		label,
		height = 200
	}: {
		/** One per calendar day, `YYYY-MM-DD`. */
		columns: Column[];
		label: string;
		height?: number;
	} = $props();

	const PAD = { top: 20, right: 8, bottom: 26, left: 40 };
	const MAX_BAR = 24;
	const RADIUS = 4;

	let width = $state(640);

	// Calendar days, not instants: formatted in UTC with a fixed locale so the
	// server and the browser always agree on the label.
	const dayFormat = new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', timeZone: 'UTC' });
	const longFormat = new Intl.DateTimeFormat('en', { weekday: 'short', month: 'short', day: 'numeric', timeZone: 'UTC' });
	const asDate = (day: string) => new Date(`${day}T00:00:00Z`);

	const max = $derived(Math.max(0, ...columns.map((c) => c.v)));
	// Headroom above the peak, so its label always fits over it.
	const ticks = $derived(niceTicks(max * 1.15));
	const top = $derived(ticks[ticks.length - 1]);
	const plotW = $derived(Math.max(1, width - PAD.left - PAD.right));
	const plotH = $derived(height - PAD.top - PAD.bottom);
	const band = $derived(plotW / Math.max(1, columns.length));
	// Thin marks: never wider than 24px, and always a gap between neighbours.
	const barW = $derived(Math.max(2, Math.min(MAX_BAR, band - 4)));

	const y = (v: number) => PAD.top + plotH - (v / top) * plotH;
	const cx = (i: number) => PAD.left + band * i + band / 2;

	/** A column with a rounded top and a square foot on the baseline. */
	function columnPath(i: number, v: number): string {
		const x0 = cx(i) - barW / 2;
		const h = (v / top) * plotH;
		if (h <= 0) return '';
		const r = Math.min(RADIUS, barW / 2, h);
		const base = y(0);
		const t = base - h;
		return `M${x0},${base}V${t + r}Q${x0},${t} ${x0 + r},${t}H${x0 + barW - r}Q${x0 + barW},${t} ${x0 + barW},${t + r}V${base}Z`;
	}

	const xTicks = $derived.by(() => {
		if (columns.length < 2) return columns.map((_, i) => i);
		const count = Math.max(2, Math.min(7, Math.floor(plotW / 80)));
		return [...new Set(Array.from({ length: count }, (_, k) => Math.round((k / (count - 1)) * (columns.length - 1))))];
	});

	const peak = $derived(columns.reduce((best, c, i) => (c.v > (columns[best]?.v ?? -1) ? i : best), 0));
	let active = $state<number | null>(null);
</script>

<div class="relative" bind:clientWidth={width}>
	<svg {width} {height} role="img" aria-label="{label} per day: peak {full(max)}" class="block">
		{#each ticks as tick (tick)}
			<line x1={PAD.left} x2={width - PAD.right} y1={y(tick)} y2={y(tick)} class="stroke-chart-grid" stroke-width="1" />
			<text x={PAD.left - 8} y={y(tick)} dy="0.32em" text-anchor="end" class="fill-muted-foreground text-[11px] tabular-nums">{compact(tick)}</text>
		{/each}

		{#each columns as column, i (column.day)}
			<path d={columnPath(i, column.v)} class="fill-chart-1 transition-opacity" opacity={active === null || active === i ? 1 : 0.55} />
		{/each}

		{#if max > 0}
			<text x={cx(peak)} y={y(max) - 6} text-anchor="middle" class="fill-foreground text-[11px] font-medium tabular-nums">{full(max)}</text>
		{/if}

		{#each xTicks as i, k (i)}
			<text
				x={cx(i)}
				y={height - 8}
				text-anchor={xTicks.length > 1 && k === 0 ? 'start' : xTicks.length > 1 && k === xTicks.length - 1 ? 'end' : 'middle'}
				dx={xTicks.length > 1 && k === 0 ? -barW / 2 : xTicks.length > 1 && k === xTicks.length - 1 ? barW / 2 : 0}
				class="fill-muted-foreground text-[11px]"
			>{dayFormat.format(asDate(columns[i].day))}</text>
		{/each}

		<!-- Hit targets: the whole band, not just the painted column. -->
		{#each columns as column, i (column.day)}
			<rect
				x={PAD.left + band * i}
				y={PAD.top}
				width={band}
				height={plotH}
				fill="transparent"
				role="button"
				tabindex="0"
				aria-label="{longFormat.format(asDate(column.day))}: {full(column.v)} {label.toLowerCase()}"
				class="outline-none"
				onpointerenter={() => (active = i)}
				onpointerleave={() => (active = null)}
				onfocus={() => (active = i)}
				onblur={() => (active = null)}
			/>
		{/each}
	</svg>

	{#if active !== null && columns[active]}
		{@const left = cx(active)}
		<div
			class="pointer-events-none absolute top-0 z-10 rounded-md border bg-popover px-2.5 py-1.5 text-xs shadow-lg whitespace-nowrap"
			style="left: {left}px; transform: translateX({left > width / 2 ? 'calc(-100% - 14px)' : '14px'})"
		>
			<div class="text-sm font-semibold tabular-nums">{full(columns[active].v)}</div>
			<div class="flex items-center gap-1.5 text-muted-foreground">
				<span class="inline-block size-2 rounded-sm bg-chart-1"></span>{label}
			</div>
			<div class="text-muted-foreground">{longFormat.format(asDate(columns[active].day))}</div>
		</div>
	{/if}
</div>

<details class="mt-2 text-xs text-muted-foreground">
	<summary class="cursor-pointer select-none hover:text-foreground">Show as table</summary>
	<div class="mt-2 max-h-56 overflow-auto rounded-md border">
		<table class="w-full tabular-nums">
			<thead class="sticky top-0 bg-card">
				<tr><th class="px-2 py-1 text-left font-medium">Day</th><th class="px-2 py-1 text-right font-medium">{label}</th></tr>
			</thead>
			<tbody>
				{#each columns as column (column.day)}
					<tr class="border-t">
						<td class="px-2 py-0.5">{longFormat.format(asDate(column.day))}</td>
						<td class="px-2 py-0.5 text-right text-foreground">{full(column.v)}</td>
					</tr>
				{/each}
			</tbody>
		</table>
	</div>
</details>
