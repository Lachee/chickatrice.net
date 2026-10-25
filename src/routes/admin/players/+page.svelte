<script lang="ts">
	import * as Card from '#lib/components/ui/card/index.ts';
	import * as Table from '#lib/components/ui/table/index.ts';
	import { Badge } from '#lib/components/ui/badge/index.ts';
	import { Button } from '#lib/components/ui/button/index.ts';
	import { Input } from '#lib/components/ui/input/index.ts';
	import RoleBadges from '#lib/components/admin/RoleBadges.svelte';
	import Time from '#lib/components/admin/Time.svelte';
	import { flag } from '#lib/countries.ts';
	import SearchIcon from '@lucide/svelte/icons/search';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();

	const heading = $derived(
		{
			recent: 'Newest accounts',
			name: `Names starting “${data.q}”, or with that client id`,
			email: `Accounts with the email ${data.q}`,
			ip: `Accounts that connected from ${data.q}`
		}[data.by]
	);
</script>

<form method="GET" class="mb-4 flex max-w-xl gap-2" role="search">
	<Input name="q" value={data.q} placeholder="Name, email, IP address or client id" aria-label="Search players" autofocus />
	<Button type="submit"><SearchIcon /> Search</Button>
</form>

<Card.Root>
	<Card.Header>
		<Card.Title>{heading}</Card.Title>
		<Card.Description>{data.players.length === 50 ? 'The first 50.' : `${data.players.length} found.`}</Card.Description>
	</Card.Header>
	<Card.Content>
		{#if data.players.length}
			<Table.Root>
				<Table.Header>
					<Table.Row>
						<Table.Head>Name</Table.Head>
						<Table.Head>Email</Table.Head>
						<Table.Head>Registered</Table.Head>
						<Table.Head>Last login</Table.Head>
						<Table.Head>Client</Table.Head>
					</Table.Row>
				</Table.Header>
				<Table.Body>
					{#each data.players as player (player.id)}
						<Table.Row>
							<Table.Cell>
								<div class="flex flex-wrap items-center gap-1.5">
									{#if player.country}<span aria-hidden="true">{flag(player.country)}</span>{/if}
									<a href="/admin/players/{encodeURIComponent(player.name)}" class="font-medium underline-offset-4 hover:underline">{player.name}</a>
									<RoleBadges admin={player.admin} privlevel={player.privlevel} />
									{#if !player.active}<Badge variant="destructive">Not activated</Badge>{/if}
								</div>
							</Table.Cell>
							<Table.Cell class="max-w-56 truncate text-muted-foreground">{player.email || '—'}</Table.Cell>
							<Table.Cell><Time at={player.registeredAt} /></Table.Cell>
							<Table.Cell><Time at={player.lastLogin} relative empty="Never" /></Table.Cell>
							<Table.Cell class="text-muted-foreground">{player.clientVersion ?? '—'}</Table.Cell>
						</Table.Row>
					{/each}
				</Table.Body>
			</Table.Root>
		{:else}
			<p class="text-sm text-muted-foreground">Nobody found.</p>
		{/if}
	</Card.Content>
</Card.Root>
