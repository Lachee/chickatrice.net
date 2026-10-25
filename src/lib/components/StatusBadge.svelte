<script lang="ts">
	import CircleCheckIcon from '@lucide/svelte/icons/circle-check';
	import CircleXIcon from '@lucide/svelte/icons/circle-x';

	let { online }: { online: boolean } = $props();
</script>

<!-- Status is never colour alone: an icon and a word go with it. -->
<span
	class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium {online
		? 'border-success/30 bg-success/10 text-success'
		: 'border-destructive/30 bg-destructive/10 text-destructive'}"
>
	{#if online}<CircleCheckIcon class="size-3.5" /> Online{:else}<CircleXIcon class="size-3.5" /> Offline{/if}
</span>
