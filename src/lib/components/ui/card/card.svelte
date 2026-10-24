<script lang="ts">
	import { cn, type WithElementRef } from "#lib/utils.js";
	import type { HTMLAttributes } from "svelte/elements";

	let {
		ref = $bindable(null),
		class: className,
		children,
		size = "default",
		...restProps
	}: WithElementRef<HTMLAttributes<HTMLDivElement>> & { size?: "default" | "sm" } = $props();
</script>

<div
	bind:this={ref}
	data-slot="card"
	data-size={size}
	class={cn("shadow-[0_0.5em_1em_-0.125em_rgb(10_10_10/0.1),0_0_0_1px_rgb(10_10_10/0.02)] bg-card text-card-foreground gap-(--card-spacing) overflow-hidden rounded-[15px] py-(--card-spacing) text-sm [--card-spacing:--spacing(4)] has-data-[slot=card-footer]:pb-0 has-[>img:first-child]:pt-0 data-[size=sm]:[--card-spacing:--spacing(3)] data-[size=sm]:has-data-[slot=card-footer]:pb-0 *:[img:first-child]:rounded-t-[15px] *:[img:last-child]:rounded-b-[15px] group/card flex flex-col", className)}
	{...restProps}
>
	{@render children?.()}
</div>
