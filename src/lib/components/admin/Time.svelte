<script lang="ts">
	import { onMount } from 'svelte';

	let {
		at,
		/** "3h ago" rather than a date and time; the full one is in the tooltip either way. */
		relative = false,
		/** Shown when there's no time at all. */
		empty = '—'
	}: { at: number | null; relative?: boolean; empty?: string } = $props();

	// The reader's time zone is only known in the browser, so the server renders
	// UTC and the page switches to local time once it's running.
	let mounted = $state(false);
	onMount(() => (mounted = true));

	const local = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });
	const utc = new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'UTC' });
	const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

	function ago(seconds: number): string {
		const delta = seconds - Date.now() / 1000;
		const abs = Math.abs(delta);
		if (abs < 60) return delta < 0 ? 'just now' : 'in a moment';
		if (abs < 3600) return rtf.format(Math.round(delta / 60), 'minute');
		if (abs < 86400) return rtf.format(Math.round(delta / 3600), 'hour');
		if (abs < 86400 * 45) return rtf.format(Math.round(delta / 86400), 'day');
		if (abs < 86400 * 365) return rtf.format(Math.round(delta / (86400 * 30)), 'month');
		return rtf.format(Math.round(delta / (86400 * 365)), 'year');
	}

	const date = $derived(at === null ? null : new Date(at * 1000));
	const exact = $derived(date ? (mounted ? local.format(date) : `${utc.format(date)} UTC`) : '');
</script>

{#if date}
	<time datetime={date.toISOString()} title={exact} class="whitespace-nowrap tabular-nums">{relative && mounted ? ago(at!) : exact}</time>
{:else}
	<span class="text-muted-foreground">{empty}</span>
{/if}
