<script lang="ts">
	import { full } from '#lib/charts.js';
	import { countryName, flag } from '#lib/countries.js';

	let {
		countries,
		total
	}: {
		countries: { country: string; players: number }[];
		/** Players across every country, not just the ones listed, for the shares. */
		total: number;
	} = $props();

	const max = $derived(Math.max(1, ...countries.map((c) => c.players)));
	const percent = (n: number) => (total ? `${Math.round((n / total) * 100)}%` : '');
</script>

{#if countries.length}
	<ol class="space-y-2.5">
		{#each countries as row (row.country)}
			<li class="grid grid-cols-[minmax(0,9rem)_1fr_auto] items-center gap-3 text-sm">
				<span class="truncate" title={countryName(row.country)}>
					<span aria-hidden="true">{flag(row.country)}</span>
					{countryName(row.country)}
				</span>
				<span class="h-2.5 rounded-r-[4px] bg-chart-1" style="width: max(2px, {(row.players / max) * 100}%)" aria-hidden="true"></span>
				<span class="text-right tabular-nums">
					{full(row.players)}
					<span class="ml-1 inline-block w-9 text-xs text-muted-foreground">{percent(row.players)}</span>
				</span>
			</li>
		{/each}
	</ol>
{:else}
	<p class="text-sm text-muted-foreground">Nobody has played recently.</p>
{/if}
