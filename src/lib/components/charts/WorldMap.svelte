<script lang="ts" module>
	import { geoNaturalEarth1, geoPath } from 'd3-geo';
	import { feature } from 'topojson-client';
	import type { Topology, GeometryCollection } from 'topojson-specification';
	import world from 'world-atlas/countries-110m.json';
	import { COUNTRY_BY_NUMERIC, countryName } from '#lib/countries.ts';

	const WIDTH = 960;

	// Projected once per process, not per render: the shapes never change.
	const topology = world as unknown as Topology<{ countries: GeometryCollection<{ name: string }> }>;
	const land = feature(topology, topology.objects.countries).features.filter((f) => f.id !== '010'); // no Antarctica
	const collection = { type: 'FeatureCollection' as const, features: land };
	const projection = geoNaturalEarth1().fitWidth(WIDTH, collection);
	// Whole pixels: plenty at this size, and it roughly halves the markup.
	const path = geoPath(projection).digits(0);
	const [[, top], [, bottom]] = path.bounds(collection);
	projection.translate([projection.translate()[0], projection.translate()[1] - top]);
	const HEIGHT = Math.ceil(bottom - top);

	const SHAPES = land.map((f) => {
		const code = COUNTRY_BY_NUMERIC.get(String(f.id)) ?? null;
		return { key: String(f.id ?? f.properties.name), code, name: code ? countryName(code) : f.properties.name, d: path(f) ?? '' };
	});
</script>

<script lang="ts">
	import { full } from '#lib/charts.ts';

	let {
		countries,
		total
	}: {
		/** Every country with players, most first. */
		countries: { country: string; players: number }[];
		total: number;
	} = $props();

	/** Steps of the sequential ramp in layout.css (`--map-1` … `--map-5`). */
	const STEPS = 5;

	const players = $derived(new Map(countries.map((c) => [c.country, c.players])));
	const max = $derived(countries[0]?.players ?? 0);

	// Counts are heavily skewed (one country dwarfs the rest), so the bins are
	// log-spaced: each upper bound is max^(k/STEPS), deduplicated for small maxima.
	const bins = $derived.by(() => {
		if (max < 1) return [];
		const uppers = [...new Set(Array.from({ length: STEPS }, (_, k) => Math.max(1, Math.round(max ** ((k + 1) / STEPS)))))];
		return uppers.map((upper, i) => {
			const lower = i === 0 ? 1 : uppers[i - 1] + 1;
			// Fewer bins than steps still span the ramp, lightest to darkest.
			const step = uppers.length === 1 ? STEPS : 1 + Math.round((i * (STEPS - 1)) / (uppers.length - 1));
			return { lower, upper, step, label: lower === upper ? full(upper) : `${full(lower)}–${full(upper)}` };
		});
	});

	const fill = (code: string | null) => {
		const n = code ? (players.get(code) ?? 0) : 0;
		if (n < 1) return 'var(--map-0)';
		const bin = bins.find((b) => n <= b.upper) ?? bins[bins.length - 1];
		return `var(--map-${bin.step})`;
	};

	const percent = (n: number) => (total ? `${Math.round((n / total) * 100)}%` : '');

	let container = $state<HTMLDivElement>();
	let width = $state(WIDTH);
	let active = $state<{ index: number; x: number; y: number } | null>(null);
	const activeShape = $derived(active ? SHAPES[active.index] : null);
	const activePlayers = $derived(activeShape?.code ? (players.get(activeShape.code) ?? 0) : 0);

	function track(index: number, event: PointerEvent) {
		const rect = container!.getBoundingClientRect();
		active = { index, x: event.clientX - rect.left, y: event.clientY - rect.top };
	}

	const summary = $derived(
		countries.length
			? `World map of players by country. Most are from ${countryName(countries[0].country)} (${full(countries[0].players)}), across ${countries.length} countries.`
			: 'World map of players by country. Nobody has played recently.'
	);
</script>

<div class="relative" bind:this={container} bind:clientWidth={width}>
	<svg viewBox="0 0 {WIDTH} {HEIGHT}" class="block h-auto w-full" role="img" aria-label={summary}>
		{#each SHAPES as shape, i (shape.key)}
			<path
				d={shape.d}
				fill={fill(shape.code)}
				class="stroke-card"
				stroke-width="0.6"
				stroke-linejoin="round"
				role="presentation"
				onpointerenter={(e) => track(i, e)}
				onpointermove={(e) => track(i, e)}
				onpointerleave={() => (active = null)}
			/>
		{/each}
		<!-- Drawn again on top, so its outline isn't hidden under its neighbours'. -->
		{#if activeShape}
			<path d={activeShape.d} fill="none" class="pointer-events-none stroke-heading" stroke-width="1.2" stroke-linejoin="round" />
		{/if}
	</svg>

	{#if active && activeShape}
		<div
			class="pointer-events-none absolute z-10 rounded-md border bg-popover px-2.5 py-1.5 text-xs whitespace-nowrap shadow-lg"
			style="left: {active.x}px; top: {active.y}px; transform: translate({active.x > width / 2 ? 'calc(-100% - 14px)' : '14px'}, -50%)"
		>
			<div class="font-medium text-heading">{activeShape.name}</div>
			{#if activePlayers}
				<div class="tabular-nums">
					<span class="text-sm font-semibold">{full(activePlayers)}</span>
					{activePlayers === 1 ? 'player' : 'players'}
					<span class="text-muted-foreground">· {percent(activePlayers)}</span>
				</div>
			{:else}
				<div class="text-muted-foreground">No players</div>
			{/if}
		</div>
	{/if}
</div>

{#if bins.length}
	<div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-muted-foreground" aria-hidden="true">
		<span>Players</span>
		{#each bins as bin (bin.step)}
			<span class="inline-flex items-center gap-1.5 tabular-nums">
				<span class="inline-block size-3 rounded-sm" style="background: var(--map-{bin.step})"></span>{bin.label}
			</span>
		{/each}
		<span class="inline-flex items-center gap-1.5">
			<span class="inline-block size-3 rounded-sm ring-1 ring-border ring-inset" style="background: var(--map-0)"></span>None
		</span>
	</div>
{/if}
