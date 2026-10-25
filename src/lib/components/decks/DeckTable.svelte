<script lang="ts">
	import * as Table from '#lib/components/ui/table/index.ts';
	import { Button } from '#lib/components/ui/button/index.ts';
	import Time from '#lib/components/admin/Time.svelte';
	import VisibilityBadge from './VisibilityBadge.svelte';
	import type { DeckVisibility } from '#lib/decks.ts';
	import DownloadIcon from '@lucide/svelte/icons/download';
	import FolderIcon from '@lucide/svelte/icons/folder';

	type Row = {
		slug: string;
		name: string;
		folder: string;
		uploadedAt: number;
		visibility: DeckVisibility;
		cards: number | null;
	};

	type Art = Record<string, { url: string; artist: string | null }>;

	let {
		decks,
		showVisibility = true,
		/** Each deck's cover art by slug, streamed in after the table. */
		art
	}: { decks: Row[]; showVisibility?: boolean; art?: Promise<Art> } = $props();
</script>

<Table.Root>
	<Table.Header>
		<Table.Row>
			<Table.Head class="w-14"><span class="sr-only">Art</span></Table.Head>
			<Table.Head>Name</Table.Head>
			<Table.Head class="text-right">Cards</Table.Head>
			{#if showVisibility}<Table.Head>Shared</Table.Head>{/if}
			<Table.Head>Saved</Table.Head>
			<Table.Head><span class="sr-only">Download</span></Table.Head>
		</Table.Row>
	</Table.Header>
	<Table.Body>
		{#each decks as deck (deck.slug)}
			<Table.Row>
				<Table.Cell class="pr-0">
					<a href="/decks/{deck.slug}" tabindex="-1" aria-hidden="true" class="block h-9 w-12 overflow-hidden rounded-md bg-muted">
						{#await art then images}
							{@const image = images?.[deck.slug]}
							{#if image}
								<img src={image.url} alt="" title={image.artist ? `Art by ${image.artist}` : undefined} loading="lazy" class="size-full object-cover" />
							{/if}
						{/await}
					</a>
				</Table.Cell>
				<Table.Cell class="max-w-80">
					<a href="/decks/{deck.slug}" class="block truncate font-medium underline-offset-4 hover:underline">{deck.name}</a>
					{#if deck.folder}
						<span class="flex items-center gap-1 truncate text-xs text-muted-foreground"><FolderIcon class="size-3 shrink-0" /> {deck.folder}</span>
					{/if}
				</Table.Cell>
				<Table.Cell class="text-right tabular-nums text-muted-foreground">{deck.cards ?? '?'}</Table.Cell>
				{#if showVisibility}<Table.Cell><VisibilityBadge visibility={deck.visibility} /></Table.Cell>{/if}
				<Table.Cell><Time at={deck.uploadedAt} relative /></Table.Cell>
				<Table.Cell class="text-right">
					<Button href="/decks/{deck.slug}/download" variant="ghost" size="icon-sm" aria-label="Download {deck.name}" title="Download for Cockatrice" data-sveltekit-reload>
						<DownloadIcon />
					</Button>
				</Table.Cell>
			</Table.Row>
		{/each}
	</Table.Body>
</Table.Root>
