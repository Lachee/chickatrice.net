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

	const heroButton = 'h-11 px-4 text-base';
	// White outline on the hero, filled white on hover (Bulma's "is-inverted is-outlined").
	const outlined = `${heroButton} border-2 border-white/80 bg-transparent text-white hover:border-white hover:bg-white hover:text-primary dark:bg-transparent dark:border-white/80`;
</script>

<!--
	The old site's Bulma hero, filling down to the footer. It's the navbar's colour, so the
	two read as one surface; the glow, shade and dots are layered on in layout.css.
-->
<section class="hero full-bleed relative isolate flex flex-1 items-center overflow-hidden text-white first:-mt-8 last:-mb-8">
	<div class="hero-dots absolute inset-0 -z-10" aria-hidden="true"></div>
	<div class="mx-auto grid w-full max-w-6xl grid-cols-1 gap-10 px-4 py-12 lg:grid-cols-[1fr_24rem] lg:items-center lg:py-16">
		<div>
			<div class="flex items-center gap-5">
				<!-- The art is a 16px grid: 80/96/112px keep each of its pixels a whole 5/6/7 screen pixels. -->
				<img
					src="/logo.png"
					alt=""
					width="112"
					height="112"
					class="size-20 shrink-0 rounded-[20px] bg-white shadow-[0_12px_30px_-10px_rgb(80_10_10/0.45)] [image-rendering:pixelated] sm:size-24 lg:size-28"
				/>
				<div>
					<h1 class="text-5xl font-bold tracking-tight text-white sm:text-6xl">Chickatrice</h1>
					<p class="mt-1 text-xl text-white/90 sm:text-2xl">{data.site.tagline}</p>
				</div>
			</div>
			<p class="mt-8 max-w-xl text-lg leading-relaxed text-white/90">
				A free server for playing Magic: The Gathering with Cockatrice. Sign in to set up your in-game account, then connect from
				the client.
			</p>
			<div class="mt-8 flex flex-wrap gap-3">
				<Button
					href="https://cockatrice.github.io/"
					size="lg"
					class="{heroButton} bg-white text-primary shadow-[0_8px_20px_-8px_rgb(80_10_10/0.5)] hover:bg-white/90"
					target="_blank"
					rel="noopener"
				>
					<DownloadIcon /> Get Cockatrice
				</Button>
				{#if data.site.discord}
					<Button href={data.site.discord} size="lg" variant="outline" class={outlined} target="_blank" rel="noopener">
						<MessageCircleIcon /> Join Discord
					</Button>
				{/if}
				<Button href="/stats" size="lg" variant="ghost" class="{heroButton} text-white hover:bg-white/15 hover:text-white">
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
