<script lang="ts">
	import { page } from '$app/state';
	import { Button } from '#lib/components/ui/button/index.ts';
	import { full } from '#lib/charts.ts';

	let { total, offset, limit }: { total: number; offset: number; limit: number } = $props();

	/** This page's URL with another offset, keeping its other filters. */
	function at(next: number) {
		const url = new URL(page.url.href);
		if (next > 0) url.searchParams.set('offset', String(next));
		else url.searchParams.delete('offset');
		return url.pathname + url.search;
	}
</script>

{#if total > limit}
	<div class="flex items-center justify-between gap-3 text-sm text-muted-foreground">
		<span>{full(offset + 1)}–{full(Math.min(total, offset + limit))} of {full(total)}</span>
		<div class="flex gap-2">
			<Button variant="outline" size="sm" href={at(Math.max(0, offset - limit))} disabled={offset === 0} data-sveltekit-noscroll>Newer</Button>
			<Button variant="outline" size="sm" href={at(offset + limit)} disabled={offset + limit >= total} data-sveltekit-noscroll>Older</Button>
		</div>
	</div>
{/if}
