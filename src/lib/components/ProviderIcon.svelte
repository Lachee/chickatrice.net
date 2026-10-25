<script lang="ts">
	import type { Snippet } from 'svelte';

	/** `src` is the provider's configured icon, if any; `fallback` stands in without one, or if it won't load. */
	let { src, fallback }: { src: string | null; fallback?: Snippet } = $props();

	let failed = $state(false);
</script>

{#if src && !failed}
	<img {src} alt="" class="size-4 shrink-0 object-contain" referrerpolicy="no-referrer" onerror={() => (failed = true)} />
{:else}
	{@render fallback?.()}
{/if}
