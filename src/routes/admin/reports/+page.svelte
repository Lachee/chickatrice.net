<script lang="ts">
	import * as Card from '#lib/components/ui/card/index.ts';
	import * as Table from '#lib/components/ui/table/index.ts';
	import { Badge } from '#lib/components/ui/badge/index.ts';
	import StatTile from '#lib/components/StatTile.svelte';
	import BarList from '#lib/components/admin/BarList.svelte';
	import Pager from '#lib/components/admin/Pager.svelte';
	import Time from '#lib/components/admin/Time.svelte';
	import { full } from '#lib/charts.ts';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();

	const filterLabel: Record<string, string> = {
		unresolved: 'Unresolved',
		mine: 'Assigned to me',
		open: 'Open',
		assigned: 'Assigned',
		resolved: 'Resolved',
		dismissed: 'Dismissed',
		all: 'All'
	};
	const category = (key: string) => data.categories[key.toLowerCase()] ?? key;
</script>

<div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
	<StatTile label="Waiting" value={full(data.stats.pending)} hint="open or assigned" />
	<StatTile label="Last 7 days" value={full(data.stats.last7d)} hint="{full(data.stats.last30d)} in 30 days" />
	<StatTile
		label="Time to close"
		value={data.stats.avgResolutionHours === null ? '—' : `${Math.round(data.stats.avgResolutionHours)}h`}
		hint="on average"
	/>
	<StatTile label="All time" value={full(data.stats.total)} />
</div>

<div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
	<Card.Root>
		<Card.Header>
			<Card.Title>Reports</Card.Title>
			<Card.Description>Filed by players from the client. Servatrice tells the reporter when one is closed.</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<nav class="flex flex-wrap gap-1" aria-label="Filter reports">
				{#each data.filters as option (option)}
					<a
						href="?filter={option}"
						aria-current={option === data.filter ? 'true' : undefined}
						class="rounded-md px-2.5 py-1 text-sm transition-colors {option === data.filter
							? 'bg-accent font-medium text-accent-foreground'
							: 'text-muted-foreground hover:text-foreground'}">{filterLabel[option]}</a
					>
				{/each}
			</nav>
			{#if data.reports.length}
				<Table.Root>
					<Table.Header>
						<Table.Row>
							<Table.Head>Report</Table.Head>
							<Table.Head>About</Table.Head>
							<Table.Head>From</Table.Head>
							<Table.Head>Status</Table.Head>
							<Table.Head>Filed</Table.Head>
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each data.reports as report (report.id)}
							<Table.Row>
								<Table.Cell class="max-w-sm">
									<a href="/admin/reports/{report.id}" class="font-medium underline-offset-4 hover:underline">#{report.id} {category(report.category)}</a>
									<div class="truncate text-xs text-muted-foreground">{report.description}</div>
								</Table.Cell>
								<Table.Cell><a href="/admin/players/{encodeURIComponent(report.reportedUserName)}" class="underline-offset-4 hover:underline">{report.reportedUserName}</a></Table.Cell>
								<Table.Cell class="text-muted-foreground">{report.reporterName}</Table.Cell>
								<Table.Cell>
									<Badge variant={report.status === 'open' ? 'default' : 'secondary'}>{filterLabel[report.status]}</Badge>
									{#if report.assignedName && report.status === 'assigned'}<span class="text-xs text-muted-foreground"> {report.assignedName}</span>{/if}
								</Table.Cell>
								<Table.Cell><Time at={report.createdAt} relative /></Table.Cell>
							</Table.Row>
						{/each}
					</Table.Body>
				</Table.Root>
				<Pager total={data.total} offset={data.offset} limit={data.limit} />
			{:else}
				<p class="text-sm text-muted-foreground">No reports here.</p>
			{/if}
		</Card.Content>
	</Card.Root>

	<div class="space-y-4">
		<Card.Root>
			<Card.Header><Card.Title>By category</Card.Title></Card.Header>
			<Card.Content><BarList rows={data.stats.categories} empty="No reports yet." /></Card.Content>
		</Card.Root>
		<Card.Root>
			<Card.Header>
				<Card.Title>Most reported</Card.Title>
				<Card.Description>Last 30 days</Card.Description>
			</Card.Header>
			<Card.Content>
				<BarList
					rows={data.stats.topReported.map((row) => ({ ...row, href: `/admin/players/${encodeURIComponent(row.label)}` }))}
					empty="Nobody this month."
				/>
			</Card.Content>
		</Card.Root>
	</div>
</div>
