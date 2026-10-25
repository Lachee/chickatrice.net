<script lang="ts">
	/** Umami's tracker, and its recorder when replays or heatmaps are on. Configured under `umami:` in config.yaml. */
	let {
		host,
		websiteId,
		performance = true,
		replays = false,
		heatmap = false
	}: {
		host: string;
		websiteId: string;
		performance?: boolean;
		replays?: boolean;
		heatmap?: boolean;
	} = $props();
</script>

<svelte:head>
	<script defer src="{host}/script.js" data-website-id={websiteId} data-performance={performance ? 'true' : 'false'}></script>
	{#if replays || heatmap}
		<script defer src="{host}/recorder.js" data-website-id={websiteId}></script>
	{/if}
</svelte:head>
