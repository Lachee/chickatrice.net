<script lang="ts">
	import * as Card from '#lib/components/ui/card/index.ts';
	import * as Table from '#lib/components/ui/table/index.ts';
	import { Badge } from '#lib/components/ui/badge/index.ts';
	import RoleBadges from '#lib/components/admin/RoleBadges.svelte';
	import Time from '#lib/components/admin/Time.svelte';
	import { flag } from '#lib/countries.ts';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();
</script>

<Card.Root>
	<Card.Header>
		<Card.Title>Staff</Card.Title>
		<Card.Description>
			Everyone with a staff role, and what they've done in the last 30 days.
			{#if data.staff.administer}Change someone's roles from their page.{/if}
		</Card.Description>
	</Card.Header>
	<Card.Content>
		<Table.Root>
			<Table.Header>
				<Table.Row>
					<Table.Head>Name</Table.Head>
					<Table.Head>Last login</Table.Head>
					<Table.Head class="text-right">Bans</Table.Head>
					<Table.Head class="text-right">Warnings</Table.Head>
					<Table.Head class="text-right">Website actions</Table.Head>
				</Table.Row>
			</Table.Header>
			<Table.Body>
				{#each data.members as member (member.id)}
					<Table.Row>
						<Table.Cell>
							<div class="flex flex-wrap items-center gap-1.5">
								{#if member.country}<span aria-hidden="true">{flag(member.country)}</span>{/if}
								<a href="/admin/players/{encodeURIComponent(member.name)}" class="font-medium underline-offset-4 hover:underline">{member.name}</a>
								<RoleBadges admin={member.admin} />
								{#if member.online}<Badge variant="outline" class="border-success/40 text-success">Online</Badge>{/if}
							</div>
						</Table.Cell>
						<Table.Cell><Time at={member.lastLogin} relative empty="Never" /></Table.Cell>
						<Table.Cell class="text-right tabular-nums">{member.bans30d}</Table.Cell>
						<Table.Cell class="text-right tabular-nums">{member.warnings30d}</Table.Cell>
						<Table.Cell class="text-right tabular-nums">{member.siteActions30d}</Table.Cell>
					</Table.Row>
				{/each}
			</Table.Body>
		</Table.Root>
	</Card.Content>
</Card.Root>
