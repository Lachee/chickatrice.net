<script lang="ts">
	import { onMount } from 'svelte';
	import { invalidate } from '$app/navigation';
	import * as Card from '#lib/components/ui/card/index.ts';
	import * as Table from '#lib/components/ui/table/index.ts';
	import { Badge } from '#lib/components/ui/badge/index.ts';
	import { Input } from '#lib/components/ui/input/index.ts';
	import Notice from '#lib/components/Notice.svelte';
	import RoleBadges from '#lib/components/admin/RoleBadges.svelte';
	import Time from '#lib/components/admin/Time.svelte';
	import { duration, full } from '#lib/charts.ts';
	import { flag } from '#lib/countries.ts';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();

	let filter = $state('');
	const players = $derived(
		filter
			? data.players.filter((p) => [p.name, p.ip, p.clientId, p.clientVersion ?? ''].some((v) => v.toLowerCase().includes(filter.toLowerCase())))
			: data.players
	);

	/** How many accounts share each address: several on one is worth a look. */
	const perAddress = $derived(
		data.players.reduce((map, p) => (p.ip ? map.set(p.ip, (map.get(p.ip) ?? 0) + 1) : map), new Map<string, number>())
	);

	// Session lengths tick along with the clock, and the list refreshes itself every half minute.
	let now = $state(Date.now() / 1000);
	onMount(() => {
		const clock = setInterval(() => (now = Date.now() / 1000), 1000);
		const refresh = setInterval(() => invalidate('chickatrice:online'), 30_000);
		return () => {
			clearInterval(clock);
			clearInterval(refresh);
		};
	});
</script>

{#if !data.serverOnline}
	<Notice type="error" class="mb-4">
		Servatrice isn't reporting in. These are the sessions it had open when it stopped; it closes them when it next starts.
	</Notice>
{/if}

<Card.Root>
	<Card.Header>
		<Card.Title>Connected now</Card.Title>
		<Card.Description>
			{full(data.players.length)} logged in{#if data.reportedUsers !== data.players.length}; Servatrice counts {full(data.reportedUsers)} connections{/if}.
			Refreshes every 30 seconds.
		</Card.Description>
	</Card.Header>
	<Card.Content class="space-y-4">
		<Input placeholder="Filter by name, address, client id or version" bind:value={filter} class="max-w-sm" aria-label="Filter" />
		{#if players.length}
			<Table.Root>
				<Table.Header>
					<Table.Row>
						<Table.Head>Player</Table.Head>
						<Table.Head>Connected</Table.Head>
						<Table.Head>Client</Table.Head>
						{#if data.staff.moderate}<Table.Head>Address</Table.Head><Table.Head>Client id</Table.Head>{/if}
					</Table.Row>
				</Table.Header>
				<Table.Body>
					{#each players as player (player.sessionId)}
						<Table.Row>
							<Table.Cell>
								<div class="flex flex-wrap items-center gap-1.5">
									{#if player.country}<span aria-hidden="true">{flag(player.country)}</span>{/if}
									{#if player.accountId !== null && data.staff.moderate}
										<a href="/admin/players/{encodeURIComponent(player.name)}" class="font-medium underline-offset-4 hover:underline">{player.name}</a>
									{:else}
										<span class="font-medium">{player.name}</span>
									{/if}
									{#if player.accountId === null}<Badge variant="outline">Guest</Badge>{/if}
									<RoleBadges admin={player.admin} privlevel={player.privlevel} />
								</div>
							</Table.Cell>
							<Table.Cell>
								<span class="tabular-nums">{duration(Math.max(0, now - player.since))}</span>
								<span class="text-xs text-muted-foreground"> · since <Time at={player.since} /></span>
							</Table.Cell>
							<Table.Cell class="text-muted-foreground">
								{player.clientVersion ?? '—'}
								{#if player.connectionType === 'websocket'}<Badge variant="secondary">WebSocket</Badge>{/if}
							</Table.Cell>
							{#if data.staff.moderate}
								<Table.Cell>
									<a href="/admin/players?q={encodeURIComponent(player.ip)}" class="font-mono text-xs underline-offset-4 hover:underline">{player.ip}</a>
									{#if (perAddress.get(player.ip) ?? 0) > 1}
										<Badge variant="destructive" title="Accounts connected from this address">×{perAddress.get(player.ip)}</Badge>
									{/if}
								</Table.Cell>
								<Table.Cell class="font-mono text-xs text-muted-foreground">{player.clientId || '—'}</Table.Cell>
							{/if}
						</Table.Row>
					{/each}
				</Table.Body>
			</Table.Root>
		{:else}
			<p class="text-sm text-muted-foreground">{filter ? 'Nobody matches that.' : 'Nobody is connected.'}</p>
		{/if}
	</Card.Content>
</Card.Root>
