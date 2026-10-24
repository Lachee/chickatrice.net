<script lang="ts">
	import type { Snippet } from 'svelte';
	import { cn } from '#lib/utils.js';
	import CircleCheckIcon from '@lucide/svelte/icons/circle-check';
	import CircleAlertIcon from '@lucide/svelte/icons/circle-alert';
	import InfoIcon from '@lucide/svelte/icons/info';

	let {
		type = 'info',
		class: className,
		children
	}: { type?: 'success' | 'error' | 'info'; class?: string; children: Snippet } = $props();

	const Icon = $derived(type === 'success' ? CircleCheckIcon : type === 'error' ? CircleAlertIcon : InfoIcon);
</script>

<div
	role={type === 'error' ? 'alert' : 'status'}
	class={cn(
		'flex items-start gap-2.5 rounded-lg border px-3.5 py-2.5 text-sm',
		type === 'success' && 'border-success/30 bg-success/10',
		type === 'error' && 'border-destructive/30 bg-destructive/10',
		type === 'info' && 'border-border bg-muted/60',
		className
	)}
>
	<Icon
		class={cn('mt-0.5 size-4 shrink-0', type === 'success' && 'text-success', type === 'error' && 'text-destructive', type === 'info' && 'text-muted-foreground')}
	/>
	<div class="min-w-0">{@render children()}</div>
</div>
