<script lang="ts">
	import * as Card from '#lib/components/ui/card/index.ts';
	import * as Avatar from '#lib/components/ui/avatar/index.ts';
	import { Button } from '#lib/components/ui/button/index.ts';
	import Notice from '#lib/components/Notice.svelte';
	import DeckTable from '#lib/components/decks/DeckTable.svelte';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();
</script>

<svelte:head>
	<title>{data.owner.name}'s decks · Chickatrice</title>
</svelte:head>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
	<div class="flex items-center gap-4">
		<Avatar.Root class="size-12 rounded-xl after:rounded-xl">
			{#if data.owner.hasAvatar}
				<Avatar.Image src="/api/user/{encodeURIComponent(data.owner.name)}/avatar" alt="" class="rounded-xl" />
			{/if}
			<Avatar.Fallback class="rounded-xl">{data.owner.name.slice(0, 2).toUpperCase()}</Avatar.Fallback>
		</Avatar.Root>
		<div>
			<h1 class="text-2xl font-semibold tracking-tight">{data.owner.name}'s decks</h1>
			<p class="text-sm text-muted-foreground">
				{data.everything ? 'All of them, private ones included.' : 'The ones they share publicly.'}
			</p>
		</div>
	</div>
	{#if data.own}
		<Button href="/decks" variant="outline">Manage your decks</Button>
	{/if}
</div>

{#if data.everything && !data.own}
	<Notice type="info" class="mb-4">You see every deck here because you're an admin. Others only see the public ones.</Notice>
{/if}

<Card.Root>
	<Card.Content>
		{#if data.decks.length}
			<DeckTable decks={data.decks} showVisibility={data.everything} />
		{:else}
			<p class="text-sm text-muted-foreground">{data.everything ? 'No decks.' : 'No public decks.'}</p>
		{/if}
	</Card.Content>
</Card.Root>
