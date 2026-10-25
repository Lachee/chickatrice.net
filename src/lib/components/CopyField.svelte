<script lang="ts">
	import CopyIcon from '@lucide/svelte/icons/copy';
	import CheckIcon from '@lucide/svelte/icons/check';
	import { track } from '#lib/umami.ts';

	/** `event` is reported to Umami on each copy, with the field's label. */
	let { label, value, event }: { label: string; value: string; event?: string } = $props();

	let copied = $state(false);
	let timer: ReturnType<typeof setTimeout> | undefined;

	async function copy() {
		try {
			await navigator.clipboard.writeText(value);
			if (event) track(event, { field: label.toLowerCase() });
			copied = true;
			clearTimeout(timer);
			timer = setTimeout(() => (copied = false), 1500);
		} catch {
			// No clipboard (insecure context); the text is selectable anyway.
		}
	}
</script>

<div class="min-w-0">
	<div class="mb-1 text-xs text-muted-foreground">{label}</div>
	<div class="flex items-center rounded-lg border bg-background/60">
		<code class="min-w-0 flex-1 truncate px-3 py-2 font-mono text-sm select-all">{value}</code>
		<button
			type="button"
			onclick={copy}
			class="flex size-9 shrink-0 items-center justify-center rounded-r-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
			aria-label="Copy {label.toLowerCase()}"
		>
			{#if copied}<CheckIcon class="size-4 text-success" />{:else}<CopyIcon class="size-4" />{/if}
		</button>
	</div>
</div>
