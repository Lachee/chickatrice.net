<script lang="ts">
	import * as Card from '#lib/components/ui/card/index.ts';
	import * as Table from '#lib/components/ui/table/index.ts';
	import { Button } from '#lib/components/ui/button/index.ts';
	import { Input } from '#lib/components/ui/input/index.ts';
	import Pager from '#lib/components/admin/Pager.svelte';
	import Time from '#lib/components/admin/Time.svelte';
	import { full } from '#lib/charts.ts';
	import SearchIcon from '@lucide/svelte/icons/search';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();
</script>

<svelte:head><title>Recent Name Changes · Chickatrice</title></svelte:head>

<h1 class="mb-1 text-2xl font-semibold tracking-tight">Recent Name Changes</h1>
<p class="mb-6 text-sm text-muted-foreground">Who has renamed themselves lately, so a familiar face under a new name is easy to place.</p>

<form method="GET" class="mb-4 flex flex-wrap items-center gap-2" role="search">
	<Input name="q" value={data.q} placeholder="Search for a name" class="w-64" aria-label="Search name changes" />
	<Button type="submit" variant="secondary"><SearchIcon /> Search</Button>
</form>

<Card.Root>
	<Card.Header>
		<Card.Title>Name changes</Card.Title>
		<Card.Description>{full(data.total)} found, newest first. Searching matches the old or the new name.</Card.Description>
	</Card.Header>
	<Card.Content class="space-y-4">
		{#if data.changes.length}
			<Table.Root>
				<Table.Header>
					<Table.Row>
						<Table.Head>When</Table.Head>
						<Table.Head>Was</Table.Head>
						<Table.Head>Now</Table.Head>
						{#if data.canModerate}<Table.Head>Changed</Table.Head>{/if}
					</Table.Row>
				</Table.Header>
				<Table.Body>
					{#each data.changes as change (change.id)}
						<Table.Row>
							<Table.Cell><Time at={change.at} relative /></Table.Cell>
							<Table.Cell class="text-muted-foreground">{change.oldName}</Table.Cell>
							<Table.Cell class="font-medium">
								{#if data.canModerate && change.currentName}
									<a href="/admin/players/{encodeURIComponent(change.currentName)}" class="underline-offset-4 hover:underline">{change.newName}</a>
									{#if change.currentName.toLowerCase() !== change.newName.toLowerCase()}
										<span class="text-xs font-normal text-muted-foreground">(now {change.currentName})</span>
									{/if}
								{:else}
									{change.newName}
								{/if}
							</Table.Cell>
							{#if data.canModerate}
								<Table.Cell class="text-sm text-muted-foreground">{change.source === 'staff' ? 'By staff' : 'By the player'}</Table.Cell>
							{/if}
						</Table.Row>
					{/each}
				</Table.Body>
			</Table.Root>
			<Pager total={data.total} offset={data.offset} limit={data.limit} />
		{:else}
			<p class="text-sm text-muted-foreground">{data.q ? 'No name changes match.' : 'Nobody has changed their name yet.'}</p>
		{/if}
	</Card.Content>
</Card.Root>
