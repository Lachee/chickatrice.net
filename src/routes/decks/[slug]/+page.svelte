<script lang="ts">
	import { enhance, type SubmitFunction } from '$app/forms';
	import { page } from '$app/state';
	import * as Card from '#lib/components/ui/card/index.ts';
	import { Badge } from '#lib/components/ui/badge/index.ts';
	import { Button } from '#lib/components/ui/button/index.ts';
	import { Input } from '#lib/components/ui/input/index.ts';
	import { Label } from '#lib/components/ui/label/index.ts';
	import Notice from '#lib/components/Notice.svelte';
	import CopyField from '#lib/components/CopyField.svelte';
	import Textarea from '#lib/components/Textarea.svelte';
	import Time from '#lib/components/admin/Time.svelte';
	import VisibilityBadge from '#lib/components/decks/VisibilityBadge.svelte';
	import { VISIBILITIES } from '#lib/decks.ts';
	import CopyIcon from '@lucide/svelte/icons/copy';
	import DownloadIcon from '@lucide/svelte/icons/download';
	import ExternalLinkIcon from '@lucide/svelte/icons/external-link';
	import FileTextIcon from '@lucide/svelte/icons/file-text';
	import FolderIcon from '@lucide/svelte/icons/folder';
	import LayoutGridIcon from '@lucide/svelte/icons/layout-grid';
	import ListIcon from '@lucide/svelte/icons/list';
	import Trash2Icon from '@lucide/svelte/icons/trash-2';
	import type { PageProps } from './$types';

	let { data, form }: PageProps = $props();

	const deck = $derived(data.deck);

	let pending = $state<string | null>(null);
	const submit =
		(name: string): SubmitFunction =>
		() => {
			pending = name;
			return async ({ update }) => {
				await update({ reset: false });
				pending = null;
			};
		};

	const feedback = (action: string) => (form?.action === action ? form : null);
	const shareUrl = $derived(`${page.url.origin}/decks/${deck.slug}`);
	const sourceName = $derived(deck.sourceUrl ? new URL(deck.sourceUrl).hostname.replace(/^www\./, '') : null);
	const scryfall = (name: string) => `https://scryfall.com/search?q=${encodeURIComponent(`!"${name}"`)}`;

	type Images = Awaited<PageProps['data']['images']>;
	let view = $state<'images' | 'list'>('images');
</script>

<svelte:head>
	<title>{deck.name} · Chickatrice</title>
	{#if deck.visibility === 'private'}<meta name="robots" content="noindex" />{/if}
	<meta property="og:title" content={deck.name} />
	<meta property="og:description" content={deck.comments || `A deck by ${deck.ownerName}${deck.cards ? `, ${deck.cards} cards` : ''}.`} />
</svelte:head>

{#snippet result(action: string)}
	{@const f = feedback(action)}
	{#if f && 'error' in f && f.error}
		<Notice type="error">{f.error}</Notice>
	{:else if f && 'message' in f && f.message}
		<Notice type="success">{f.message}</Notice>
	{/if}
{/snippet}

<a
	href={data.access.owner ? '/decks' : `/decks/by/${encodeURIComponent(deck.ownerName)}`}
	class="mb-4 inline-block text-sm text-muted-foreground underline-offset-4 hover:underline"
>
	← {data.access.owner ? 'Your decks' : `${deck.ownerName}'s decks`}
</a>

<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
	<div class="flex min-w-0 items-start gap-4">
	{#await data.images then images}
		{@const cover = deck.coverKey ? images[deck.coverKey] : null}
		{#if cover?.artCrop}
			<img
				src={cover.artCrop}
				alt=""
				title={cover.artist ? `Art by ${cover.artist}` : undefined}
				class="hidden h-16 w-22 shrink-0 rounded-lg object-cover shadow-sm sm:block"
			/>
		{/if}
	{/await}
	<div class="min-w-0">
		<h1 class="flex flex-wrap items-center gap-2 text-2xl font-semibold tracking-tight">
			<span class="break-words">{deck.name}</span>
			<VisibilityBadge visibility={deck.visibility} />
		</h1>
		<p class="flex flex-wrap items-center gap-x-1.5 text-sm text-muted-foreground">
			<span>by <a href="/decks/by/{encodeURIComponent(deck.ownerName)}" class="font-medium text-foreground underline-offset-4 hover:underline">{deck.ownerName}</a></span>
			{#if deck.cards !== null}<span>· {deck.cards} cards</span>{/if}
			<span>· saved <Time at={deck.uploadedAt} relative /></span>
			{#if deck.hash}<span title="The hash Cockatrice shows for this deck in games">· <code>{deck.hash}</code></span>{/if}
			{#if deck.folder && data.access.manage}<span class="inline-flex items-center gap-1">· <FolderIcon class="size-3.5" /> {deck.folder}</span>{/if}
		</p>
		{#if deck.tags.length}
			<div class="mt-2 flex flex-wrap gap-1">
				{#each deck.tags as tag (tag)}<Badge variant="outline">{tag}</Badge>{/each}
			</div>
		{/if}
	</div>
	</div>
	<div class="flex flex-wrap gap-2">
		<Button href="/decks/{deck.slug}/download" data-sveltekit-reload><DownloadIcon /> Download</Button>
		{#if deck.cards !== null}
			<Button href="/decks/{deck.slug}/download?format=txt" variant="outline" data-sveltekit-reload title="A plain list other deck sites can import">
				<FileTextIcon /> As text
			</Button>
		{/if}
		{#if data.canCopy}
			<form method="POST" action="?/copy" use:enhance={submit('copy')}>
				<Button type="submit" variant="outline" disabled={pending === 'copy'}><CopyIcon /> Copy to my decks</Button>
			</form>
		{/if}
		{#if deck.sourceUrl}
			<Button href={deck.sourceUrl} variant="ghost" target="_blank" rel="noopener noreferrer"><ExternalLinkIcon /> {sourceName}</Button>
		{/if}
	</div>
</div>

{#if feedback('copy')}
	<div class="mb-4">{@render result('copy')}</div>
{/if}

{#if deck.comments}
	<p class="mb-6 max-w-3xl text-sm whitespace-pre-line">{deck.comments}</p>
{/if}

{#snippet zones(images: Images | null)}
	{#if view === 'images'}
		<div class="mb-2 space-y-4">
			{#each deck.zones as zone (zone.name)}
				<Card.Root size="sm">
					<Card.Header>
						<Card.Title>{zone.label} <span class="font-normal text-muted-foreground">({zone.count})</span></Card.Title>
					</Card.Header>
					<Card.Content>
						<ul class="grid grid-cols-[repeat(auto-fill,minmax(8.5rem,1fr))] gap-2">
							{#each zone.cards as card, i (i)}
								{@const image = images?.[card.key]}
								<li>
									<a
										href={image?.uri ?? scryfall(card.name)}
										target="_blank"
										rel="noopener noreferrer"
										title="{card.count} × {card.name}{image?.artist ? ` (art by ${image.artist})` : ''}"
										class="relative block aspect-[488/680] overflow-hidden rounded-[4.75%/3.5%] bg-muted shadow-sm transition-transform hover:-translate-y-0.5"
									>
										{#if image}
											<img src={image.normal} alt={card.name} loading="lazy" class="size-full object-cover" />
										{:else}
											<span class="flex size-full items-center justify-center p-2 text-center text-xs font-medium text-muted-foreground">{card.name}</span>
										{/if}
										{#if card.count > 1}
											<span class="absolute bottom-1.5 left-1.5 rounded-md bg-black/75 px-1.5 py-0.5 text-xs font-semibold text-white tabular-nums">×{card.count}</span>
										{/if}
									</a>
								</li>
							{/each}
						</ul>
					</Card.Content>
				</Card.Root>
			{/each}
		</div>
	{:else}
		<div class="mb-2 grid items-start gap-4 md:grid-cols-2 lg:grid-cols-3">
			{#each deck.zones as zone (zone.name)}
				<Card.Root size="sm" class={zone.name === 'main' ? 'lg:col-span-2' : ''}>
					<Card.Header>
						<Card.Title>{zone.label} <span class="font-normal text-muted-foreground">({zone.count})</span></Card.Title>
					</Card.Header>
					<Card.Content>
						<ul class="gap-x-6 text-sm {zone.name === 'main' ? 'sm:columns-2' : ''}">
							{#each zone.cards as card, i (i)}
								<li class="flex break-inside-avoid gap-2 py-0.5">
									<span class="w-6 shrink-0 text-right tabular-nums text-muted-foreground">{card.count}</span>
									<a href={images?.[card.key]?.uri ?? scryfall(card.name)} target="_blank" rel="noopener noreferrer" class="min-w-0 truncate underline-offset-4 hover:underline">{card.name}</a>
									{#if card.set}<span class="ml-auto shrink-0 text-xs uppercase text-muted-foreground">{card.set}</span>{/if}
								</li>
							{/each}
						</ul>
					</Card.Content>
				</Card.Root>
			{/each}
		</div>
	{/if}
{/snippet}

{#if deck.cards === null}
	<Notice type="error" class="mb-6">This deck's file can't be read here. It can still be downloaded and opened in Cockatrice.</Notice>
{:else}
	<div class="mb-3 flex justify-end">
		<div class="inline-flex rounded-lg border p-0.5" role="group" aria-label="Show cards as">
			<Button size="sm" variant={view === 'images' ? 'secondary' : 'ghost'} aria-pressed={view === 'images'} onclick={() => (view = 'images')}>
				<LayoutGridIcon /> Images
			</Button>
			<Button size="sm" variant={view === 'list' ? 'secondary' : 'ghost'} aria-pressed={view === 'list'} onclick={() => (view = 'list')}>
				<ListIcon /> List
			</Button>
		</div>
	</div>
	{#await data.images}
		{@render zones(null)}
	{:then images}
		{@render zones(images)}
	{/await}
	<p class="mb-6 text-right text-xs text-muted-foreground">
		Card images from <a href="https://scryfall.com" target="_blank" rel="noopener noreferrer" class="underline-offset-4 hover:underline">Scryfall</a>.
	</p>
{/if}

{#if data.access.manage}
	<div class="grid gap-4 lg:grid-cols-2">
		<Card.Root>
			<Card.Header>
				<Card.Title>Deck settings</Card.Title>
				<Card.Description>
					{#if data.access.owner}
						The name is what your server deck storage in Cockatrice shows.
					{:else}
						You're changing {deck.ownerName}'s deck as an admin. It's logged.
					{/if}
				</Card.Description>
			</Card.Header>
			<Card.Content>
				<form method="POST" action="?/update" class="space-y-4" use:enhance={submit('update')}>
					{@render result('update')}
					<div class="space-y-1.5">
						<Label for="name">Name</Label>
						<Input id="name" name="name" value={deck.name} maxlength={50} required />
					</div>
					<div class="space-y-1.5">
						<Label for="comments">Description</Label>
						<Textarea id="comments" name="comments" value={deck.comments} rows={4} maxlength={4000} />
					</div>
					<fieldset class="space-y-2">
						<legend class="mb-1.5 text-sm font-medium">Who can see it</legend>
						{#each VISIBILITIES as option (option.id)}
							<label class="flex items-start gap-2.5 text-sm has-disabled:opacity-60">
								<input
									type="radio"
									name="visibility"
									value={option.id}
									checked={deck.visibility === option.id}
									disabled={Boolean(deck.publicFolder) && option.id !== 'public'}
									class="mt-0.5"
								/>
								<span>
									<span class="font-medium">{option.label}</span>
									<span class="text-muted-foreground">
										— {option.description}
										{#if option.id === 'public' && data.publicDecks}Other players can browse it from Cockatrice too.{/if}
									</span>
								</span>
							</label>
						{/each}
						{#if deck.publicFolder}
							<p class="text-xs text-muted-foreground">
								It's public because it's in <strong>{deck.publicFolder}</strong>, which is public in Cockatrice. Change that from the client.
							</p>
						{/if}
					</fieldset>
					<Button type="submit" disabled={pending === 'update'}>Save</Button>
				</form>
			</Card.Content>
		</Card.Root>

		<div class="space-y-4">
			<Card.Root>
				<Card.Header>
					<Card.Title>Share</Card.Title>
					<Card.Description>
						{#if deck.visibility === 'private'}
							While it's private this link only works for {data.access.owner ? 'you' : deck.ownerName}. Make it unlisted or public to share it.
						{:else}
							Anyone with this link can see, download and copy the deck.
						{/if}
					</Card.Description>
				</Card.Header>
				<Card.Content>
					<CopyField label="Link" value={shareUrl} />
				</Card.Content>
			</Card.Root>

			<Card.Root>
				<Card.Header>
					<Card.Title>Delete</Card.Title>
					<Card.Description>Removes it from the server deck storage too. Copies others made are theirs to keep.</Card.Description>
				</Card.Header>
				<Card.Content>
					<form
						method="POST"
						action="?/delete"
						use:enhance={(event) => {
							if (!confirm(`Delete ${deck.name}? This can't be undone.`)) return event.cancel();
							return submit('delete')(event);
						}}
					>
						<Button type="submit" variant="destructive" disabled={pending === 'delete'}><Trash2Icon /> Delete deck</Button>
					</form>
				</Card.Content>
			</Card.Root>
		</div>
	</div>
{/if}
