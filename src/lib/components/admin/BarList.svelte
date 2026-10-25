<script lang="ts">
	import { full } from '#lib/charts.ts';

	let {
		rows: all,
		total,
		limit = 10,
		empty = 'Nothing yet.'
	}: {
		/** Largest first. */
		rows: { label: string; count: number; href?: string }[];
		/** For the shares; defaults to the sum of the rows. */
		total?: number;
		limit?: number;
		empty?: string;
	} = $props();

	const rows = $derived(all.slice(0, limit));
	const sum = $derived(total ?? all.reduce((n, row) => n + row.count, 0));
	const max = $derived(Math.max(1, ...rows.map((row) => row.count)));
	const percent = (n: number) => (sum ? `${Math.round((n / sum) * 100)}%` : '');
</script>

{#if rows.length}
	<ol class="space-y-2.5">
		{#each rows as row (row.label)}
			<li class="grid grid-cols-[minmax(0,10rem)_1fr_auto] items-center gap-3 text-sm">
				{#if row.href}
					<a href={row.href} class="truncate underline-offset-4 hover:underline" title={row.label}>{row.label}</a>
				{:else}
					<span class="truncate" title={row.label}>{row.label}</span>
				{/if}
				<span class="h-2.5 rounded-r-[4px] bg-chart-1" style="width: max(2px, {(row.count / max) * 100}%)" aria-hidden="true"></span>
				<span class="text-right tabular-nums">
					{full(row.count)}
					<span class="ml-1 inline-block w-9 text-xs text-muted-foreground">{percent(row.count)}</span>
				</span>
			</li>
		{/each}
	</ol>
{:else}
	<p class="text-sm text-muted-foreground">{empty}</p>
{/if}
