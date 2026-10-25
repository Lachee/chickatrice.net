<script lang="ts">
	import { navigating } from '$app/state';
	import * as Card from '#lib/components/ui/card/index.js';
	import StatTile from '#lib/components/StatTile.svelte';
	import StatusBadge from '#lib/components/StatusBadge.svelte';
	import LineChart from '#lib/components/charts/LineChart.svelte';
	import ColumnChart from '#lib/components/charts/ColumnChart.svelte';
	import CountryBars from '#lib/components/charts/CountryBars.svelte';
	import WorldMap from '#lib/components/charts/WorldMap.svelte';
	import { compact, duration, full } from '#lib/charts.js';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();

	const timeFormat = $derived(data.range === '6h' || data.range === '24h' ? 'time' : 'date');
	// Switching range keeps the old charts up, dimmed, until the new ones arrive.
	const loading = $derived(navigating.to?.url.pathname === '/stats');
</script>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
	<div>
		<h1 class="flex items-center gap-3 text-3xl font-semibold tracking-tight">
			Statistics <StatusBadge online={data.status.online} />
		</h1>
		<p class="mt-1 text-sm text-muted-foreground">
			{#if data.status.online}
				Up for {duration(data.status.uptime)}.
			{:else if data.status.age !== null}
				Last heard from {duration(data.status.age)} ago.
			{:else}
				The server hasn't reported in yet.
			{/if}
		</p>
	</div>
</div>

<div class="mb-8 grid grid-cols-2 gap-3 md:grid-cols-4">
	<StatTile label="Players online" value={full(data.status.users)} hint="{full(data.status.games)} games running" />
	<StatTile label="Peak players" value={full(data.totals.peakUsers)} hint="most at once, last 30 days" />
	<StatTile label="Games today" value={compact(data.totals.gamesToday)} />
	<StatTile
		label="Accounts"
		value={compact(data.totals.accounts)}
		hint="{compact(data.totals.activeAccounts)} played in the last 30 days"
	/>
</div>

<!-- The one filter, in one row above everything it scopes. -->
<div class="mb-4 flex flex-wrap items-center gap-3">
	<div class="inline-flex rounded-lg border bg-card p-0.5" role="group" aria-label="Time range">
		{#each data.ranges as option (option.id)}
			<a
				href="?range={option.id}"
				data-sveltekit-noscroll
				data-sveltekit-replacestate
				aria-current={option.id === data.range ? 'true' : undefined}
				class="rounded-md px-3 py-1 text-sm transition-colors {option.id === data.range
					? 'bg-accent font-medium text-accent-foreground'
					: 'text-muted-foreground hover:text-foreground'}"
			>
				{option.id}
			</a>
		{/each}
	</div>
	<span class="text-sm text-muted-foreground">
		Server reachable {(data.availability * 100).toFixed(1)}% of the last {data.rangeLabel}
	</span>
</div>

<div class="grid gap-4 transition-opacity lg:grid-cols-2" class:opacity-60={loading}>
	<Card.Root>
		<Card.Header>
			<Card.Title>Players online</Card.Title>
			<Card.Description>Most connected at once, last {data.rangeLabel}</Card.Description>
		</Card.Header>
		<Card.Content><LineChart points={data.users} label="Players online" {timeFormat} /></Card.Content>
	</Card.Root>

	<Card.Root>
		<Card.Header>
			<Card.Title>Games running</Card.Title>
			<Card.Description>Most in progress at once, last {data.rangeLabel}</Card.Description>
		</Card.Header>
		<Card.Content><LineChart points={data.games} label="Games running" {timeFormat} /></Card.Content>
	</Card.Root>
</div>

<h2 class="mt-10 mb-4 text-lg font-semibold">Last {data.days} days</h2>

<div class="grid gap-4 lg:grid-cols-2">
	<Card.Root>
		<Card.Header>
			<Card.Title>Daily players</Card.Title>
			<Card.Description>Different accounts that connected each day</Card.Description>
		</Card.Header>
		<Card.Content><ColumnChart columns={data.dailyPlayers} label="Players" /></Card.Content>
	</Card.Root>

	<Card.Root>
		<Card.Header>
			<Card.Title>Daily games</Card.Title>
			<Card.Description>Games started each day</Card.Description>
		</Card.Header>
		<Card.Content><ColumnChart columns={data.dailyGames} label="Games" /></Card.Content>
	</Card.Root>

	<Card.Root class="lg:col-span-2">
		<Card.Header>
			<Card.Title>Where players are from</Card.Title>
			<Card.Description>
				Players by the country on their account. {full(data.countries.total)} players set one.
			</Card.Description>
		</Card.Header>
		<Card.Content class="grid gap-6 lg:grid-cols-[1fr_20rem] lg:items-start">
			<div><WorldMap countries={data.countries.countries} total={data.countries.total} /></div>
			<div>
				<h3 class="mb-3 text-xs font-medium text-muted-foreground">Top countries</h3>
				<CountryBars countries={data.countries.countries} total={data.countries.total} />
			</div>
		</Card.Content>
	</Card.Root>
</div>
