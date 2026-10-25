<script lang="ts">
	import type { HTMLSelectAttributes } from 'svelte/elements';
	import { cn } from '#lib/utils.ts';

	let { value = $bindable(), class: className, children, ...rest }: HTMLSelectAttributes = $props();
</script>

<!-- A native select, styled like Input. -->
<select
	bind:value
	class={cn(
		'h-8 min-w-0 rounded-lg border border-input bg-transparent px-2.5 py-0 pr-8 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:opacity-50 dark:bg-input/30',
		className
	)}
	{...rest}
>
	{@render children?.()}
</select>
