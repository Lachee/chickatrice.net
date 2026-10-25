<script lang="ts">
	import { navigating } from '$app/state';
	import * as Card from '#lib/components/ui/card/index.ts';
	import { Badge } from '#lib/components/ui/badge/index.ts';
	import StatTile from '#lib/components/StatTile.svelte';
	import StatusBadge from '#lib/components/StatusBadge.svelte';
	import LineChart from '#lib/components/charts/LineChart.svelte';
	import ColumnChart from '#lib/components/charts/ColumnChart.svelte';
	import BarList from '#lib/components/admin/BarList.svelte';
	import Time from '#lib/components/admin/Time.svelte';
	import { bytes, compact, duration, full } from '#lib/charts.ts';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();

	const timeFormat = $derived(data.range === '6h' || data.range === '24h' ? 'time' : 'date');
	const loading = $derived(navigating.to?.url.pathname === '/admin');
	const rate = { axis: (v: number) => `${bytes(v)}/s`, value: (v: number) => `${bytes(v)}/s` };
	const banLength = (minutes: number) => (minutes === 0 ? 'Permanent' : duration(minutes * 60));
	const registeredInWindow = $derived(data.registrations.reduce((sum, day) => sum + day.v, 0));
</script>

<div class="mb-6 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
	<StatusBadge online={data.status.online} />
	{#if data.status.online}
		Up for {duration(data.status.uptime)}, last report {data.status.age}s ago.
	{:else if data.status.age !== null}
		Servatrice last reported {duration(data.status.age)} ago.
	{:else}
		Servatrice hasn't reported in yet.
	{/if}
</div>

<div class="mb-8 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
	<StatTile label="Players online" value={full(data.status.users)} hint="{full(data.status.games)} games running" />
	<StatTile label="Staff online" value={full(data.moderators.length)} hint={data.moderators.join(', ') || 'none'} />
	<StatTile
		label="New accounts today"
		value={full(data.registrations.at(-1)?.v ?? 0)}
		hint="{compact(data.totals.accounts)} accounts in all"
	/>
	<StatTile label="Bans in effect" value={full(data.counts.activeBans)} hint="{full(data.counts.bansThisWeek)} placed this week" />
	{#if data.counts.openReports !== null}
		<StatTile label="Open reports" value={full(data.counts.openReports)} hint="{full(data.counts.warningsThisWeek)} warnings this week" />
	{:else}
		<StatTile label="Warnings this week" value={full(data.counts.warningsThisWeek)} />
	{/if}
</div>

<div class="mb-4 flex flex-wrap items-center gap-3">
	<div class="inline-flex rounded-lg border bg-card p-0.5" role="group" aria-label="Time range">
		{#each data.ranges as option (option)}
			<a
				href="?range={option}"
				data-sveltekit-noscroll
				data-sveltekit-replacestate
				aria-current={option === data.range ? 'true' : undefined}
				class="rounded-md px-3 py-1 text-sm transition-colors {option === data.range
					? 'bg-accent font-medium text-accent-foreground'
					: 'text-muted-foreground hover:text-foreground'}"
			>
				{option}
			</a>
		{/each}
	</div>
	<span class="text-sm text-muted-foreground">Last {data.rangeLabel}</span>
</div>

<div class="mb-8 grid gap-4 transition-opacity lg:grid-cols-2" class:opacity-60={loading}>
	<Card.Root>
		<Card.Header>
			<Card.Title>Players online</Card.Title>
			<Card.Description>Most connected at once</Card.Description>
		</Card.Header>
		<Card.Content><LineChart points={data.users} label="Players online" {timeFormat} /></Card.Content>
	</Card.Root>
	<Card.Root>
		<Card.Header>
			<Card.Title>Games running</Card.Title>
			<Card.Description>Most in progress at once</Card.Description>
		</Card.Header>
		<Card.Content><LineChart points={data.games} label="Games running" {timeFormat} /></Card.Content>
	</Card.Root>
	<Card.Root class="lg:col-span-2">
		<Card.Header>
			<Card.Title>Data received</Card.Title>
			<Card.Description>
				From clients, averaged over each interval. Servatrice doesn't count what it sends: its uptime table's tx_bytes is always 0.
			</Card.Description>
		</Card.Header>
		<Card.Content><LineChart points={data.received} label="Received" {timeFormat} format={rate} /></Card.Content>
	</Card.Root>
</div>

<div class="mb-8 grid gap-4 lg:grid-cols-3">
	<Card.Root>
		<Card.Header>
			<Card.Title>New accounts</Card.Title>
			<Card.Description>{full(registeredInWindow)} in the last {data.registrations.length} days (UTC)</Card.Description>
		</Card.Header>
		<Card.Content><ColumnChart columns={data.registrations} label="Accounts" /></Card.Content>
	</Card.Root>
	<Card.Root>
		<Card.Header>
			<Card.Title>Client versions</Card.Title>
			<Card.Description>{full(data.versions.total)} players who logged in in the last 30 days</Card.Description>
		</Card.Header>
		<Card.Content
			><BarList rows={data.versions.versions.map((v) => ({ label: v.label, count: v.count }))} total={data.versions.total} limit={8} /></Card.Content
		>
	</Card.Root>
	<Card.Root>
		<Card.Header>
			<Card.Title>Connections</Card.Title>
			<Card.Description>{full(data.connections.total)} sessions in the last 7 days</Card.Description>
		</Card.Header>
		<Card.Content><BarList rows={data.connections.types} total={data.connections.total} /></Card.Content>
	</Card.Root>
</div>

<div class="grid gap-4 lg:grid-cols-2">
	<Card.Root>
		<Card.Header>
			<Card.Title>Server message</Card.Title>
			<Card.Description>
				{#if data.serverMessage}Posted <Time at={data.serverMessage.at} relative />{:else}None set{/if}
			</Card.Description>
		</Card.Header>
		<Card.Content>
			{#if data.serverMessage}
				<p class="line-clamp-6 text-sm whitespace-pre-line">{data.serverMessage.message}</p>
			{/if}
			{#if data.staff.administer}
				<a href="/admin/settings#message" class="mt-3 inline-block text-sm text-primary underline-offset-4 hover:underline">Change it</a>
			{/if}
		</Card.Content>
	</Card.Root>

	{#if data.staff.moderate}
		<Card.Root>
			<Card.Header>
				<Card.Title>Latest bans</Card.Title>
				<Card.Description><a href="/admin/bans" class="underline-offset-4 hover:underline">All bans</a></Card.Description>
			</Card.Header>
			<Card.Content>
				{#if data.recentBans.length}
					<ul class="divide-y text-sm">
						{#each data.recentBans as ban (`${ban.userName}:${ban.at}`)}
							<li class="flex flex-wrap items-center justify-between gap-2 py-2">
								<span class="min-w-0 truncate">
									{#if ban.accountId !== null}
										<a href="/admin/players/{encodeURIComponent(ban.userName)}" class="font-medium underline-offset-4 hover:underline">{ban.userName}</a>
									{:else if ban.userName}
										<span class="font-medium">{ban.userName}</span>
									{:else}
										<span class="font-mono text-xs">{ban.ip || ban.clientId}</span>
									{/if}
									<span class="text-muted-foreground">by {ban.adminName ?? `#${ban.adminId}`}</span>
								</span>
								<span class="flex items-center gap-2 text-xs text-muted-foreground">
									{#if ban.effective}<Badge variant="destructive">In effect</Badge>{/if}
									{banLength(ban.minutes)} · <Time at={ban.at} relative />
								</span>
							</li>
						{/each}
					</ul>
				{:else}
					<p class="text-sm text-muted-foreground">No bans yet.</p>
				{/if}
			</Card.Content>
		</Card.Root>

		<Card.Root class="lg:col-span-2">
			<Card.Header>
				<Card.Title>Recent staff actions on this site</Card.Title>
				<Card.Description><a href="/admin/logs?tab=site" class="underline-offset-4 hover:underline">Full log</a></Card.Description>
			</Card.Header>
			<Card.Content>
				{#if data.recentActions.length}
					<ul class="divide-y text-sm">
						{#each data.recentActions as entry (entry.id)}
							<li class="flex flex-wrap items-baseline justify-between gap-2 py-2">
								<span class="min-w-0">
									<span class="font-medium">{entry.actorName}</span>
									<code class="mx-1 rounded bg-muted px-1 py-0.5 text-xs">{entry.action}</code>
									{entry.target ?? ''}
									{#if entry.details}<span class="text-muted-foreground"> — {entry.details}</span>{/if}
								</span>
								<span class="text-xs text-muted-foreground"><Time at={entry.at} relative /></span>
							</li>
						{/each}
					</ul>
				{:else}
					<p class="text-sm text-muted-foreground">Nothing done from here yet.</p>
				{/if}
			</Card.Content>
		</Card.Root>
	{/if}
</div>
