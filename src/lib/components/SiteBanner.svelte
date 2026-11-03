<script lang="ts">
	import { onMount } from 'svelte';
	import { cn } from '#lib/utils.ts';
	import InfoIcon from '@lucide/svelte/icons/info';
	import TriangleAlertIcon from '@lucide/svelte/icons/triangle-alert';
	import XIcon from '@lucide/svelte/icons/x';

	type Banner = { message: string; level: 'info' | 'warning' | 'outage'; updatedAt: number };
	let { banner }: { banner: Banner } = $props();

	// Info banners can be dismissed until the next change; warnings and outages stay.
	const STORAGE_KEY = 'banner-dismissed';
	const key = $derived(`${banner.updatedAt}:${banner.message.length}`);
	let dismissed = $state<string | null>(null);

	onMount(() => {
		try {
			dismissed = localStorage.getItem(STORAGE_KEY);
		} catch {}
	});

	function dismiss() {
		dismissed = key;
		try {
			localStorage.setItem(STORAGE_KEY, key);
		} catch {}
	}

	const Icon = $derived(banner.level === 'info' ? InfoIcon : TriangleAlertIcon);
</script>

{#if banner.level !== 'info' || dismissed !== key}
	<div
		role={banner.level === 'outage' ? 'alert' : 'status'}
		class={cn(
			'border-b text-sm',
			banner.level === 'info' && 'border-border bg-muted text-foreground',
			banner.level === 'warning' && 'border-warning/30 bg-warning/15 text-foreground',
			banner.level === 'outage' && 'border-destructive bg-destructive text-white'
		)}
	>
		<div class="mx-auto flex max-w-6xl items-start gap-2.5 px-4 py-2.5">
			<Icon class={cn('mt-0.5 size-4 shrink-0', banner.level === 'info' && 'text-muted-foreground', banner.level === 'warning' && 'text-warning')} />
			<p class="min-w-0 flex-1 whitespace-pre-line">{banner.message}</p>
			{#if banner.level === 'info'}
				<button
					type="button"
					class="-my-0.5 shrink-0 rounded p-0.5 text-muted-foreground hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring focus-visible:outline-none"
					aria-label="Dismiss"
					onclick={dismiss}
				>
					<XIcon class="size-4" />
				</button>
			{/if}
		</div>
	</div>
{/if}
