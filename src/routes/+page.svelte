<script lang="ts">
	import { onMount } from 'svelte';
	import { invalidate } from '$app/navigation';
	import { Button } from '#lib/components/ui/button/index.js';
	import * as Card from '#lib/components/ui/card/index.js';
	import StatusBadge from '#lib/components/StatusBadge.svelte';
	import CopyField from '#lib/components/CopyField.svelte';
	import LineChart from '#lib/components/charts/LineChart.svelte';
	import { duration, full } from '#lib/charts.js';
	import DownloadIcon from '@lucide/svelte/icons/download';
	import MessageCircleIcon from '@lucide/svelte/icons/message-circle';
	import ActivityIcon from '@lucide/svelte/icons/activity';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();

	// The status is cached for a few seconds server-side; every 30s is plenty.
	onMount(() => {
		const timer = setInterval(() => {
			if (document.visibilityState === 'visible') invalidate('chickatrice:status');
		}, 30_000);
		return () => clearInterval(timer);
	});

	// Bulma's "is-inverted is-outlined" buttons: white outline on the hero, filled white on hover.
	const inverted =
		'h-11 border-2 border-white bg-transparent px-4 text-base text-white hover:bg-white hover:text-primary dark:bg-transparent dark:border-white';
</script>

<!-- Like the old site's Bulma hero: full width, coral gradient, filling down to the footer. -->
<section class="full-bleed flex flex-1 items-center bg-(image:--hero) text-white first:-mt-8 last:-mb-8">
	<div class="mx-auto grid w-full max-w-6xl gap-8 px-4 py-8 lg:grid-cols-[1fr_24rem] lg:items-center lg:py-10">
		<div>
			<h1 class="text-5xl font-semibold text-white">Chickatrice</h1>
			<p class="mt-3 text-2xl font-light sm:text-3xl">{data.site.tagline}</p>
			<p class="mt-6 max-w-xl text-white/85">
				A free server for playing Magic: The Gathering with Cockatrice. Sign in to set up your in-game account, then connect from
				the client.
			</p>
			<div class="mt-8 flex flex-wrap gap-3">
				<Button href="https://cockatrice.github.io/" size="lg" variant="outline" class={inverted} target="_blank" rel="noopener">
					<DownloadIcon /> Get Cockatrice
				</Button>
				{#if data.site.discord}
					<Button href={data.site.discord} size="lg" variant="outline" class={inverted} target="_blank" rel="noopener">
						<MessageCircleIcon /> Join Discord
					</Button>
				{/if}
				<Button href="/stats" size="lg" variant="ghost" class="h-11 px-4 text-base text-white hover:bg-white/15 hover:text-white">
					<ActivityIcon /> Server statistics
				</Button>
			</div>
		</div>

		<div class="space-y-4">
			<Card.Root>
				<Card.Header>
					<Card.Title class="flex items-center justify-between gap-2">
						Server status
						<StatusBadge online={data.status.online} />
					</Card.Title>
				</Card.Header>
				<Card.Content class="space-y-4">
					<dl class="grid grid-cols-3 gap-2 text-center">
						<div>
							<dt class="text-xs text-muted-foreground">Players</dt>
							<dd class="text-2xl font-semibold">{full(data.status.users)}</dd>
						</div>
						<div>
							<dt class="text-xs text-muted-foreground">Games</dt>
							<dd class="text-2xl font-semibold">{full(data.status.games)}</dd>
						</div>
						<div>
							<dt class="text-xs text-muted-foreground">Uptime</dt>
							<dd class="text-2xl font-semibold">{data.status.online ? duration(data.status.uptime) : '—'}</dd>
						</div>
					</dl>
					<div>
						<div class="mb-1 text-xs text-muted-foreground">Players online, last 6 hours</div>
						<LineChart points={data.recent} label="Players online" height={120} />
					</div>
				</Card.Content>
			</Card.Root>

			<Card.Root>
				<Card.Header>
					<Card.Title>Connect with Cockatrice</Card.Title>
					<Card.Description>Add a new server in the client's connect dialog with these details.</Card.Description>
				</Card.Header>
				<Card.Content class="grid grid-cols-[1fr_7rem] gap-3">
					<CopyField label="Host" value={data.server.host} />
					<CopyField label="Port" value={String(data.server.port)} />
					{#if data.server.websocketPort}
						<div class="col-span-2 text-xs text-muted-foreground">
							Webatrice and other websocket clients use port {data.server.websocketPort}.
						</div>
					{/if}
				</Card.Content>
			</Card.Root>
		</div>
	</div>
</section>
