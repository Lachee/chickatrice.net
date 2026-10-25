<script lang="ts">
	import * as Card from '#lib/components/ui/card/index.ts';

	let { label, value, hint }: { label: string; value: string; hint?: string } = $props();
</script>

<Card.Root class="gap-0 px-4 py-3.5">
	<div class="text-xs text-muted-foreground">{label}</div>
	<div class="mt-1 text-2xl font-semibold tracking-tight">{value}</div>
	{#if hint}<div class="mt-0.5 text-xs text-muted-foreground">{hint}</div>{/if}
</Card.Root>
