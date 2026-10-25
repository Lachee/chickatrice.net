<script lang="ts">
	import { enhance, type SubmitFunction } from '$app/forms';
	import { page } from '$app/state';
	import * as Card from '#lib/components/ui/card/index.ts';
	import { Button } from '#lib/components/ui/button/index.ts';
	import { Input } from '#lib/components/ui/input/index.ts';
	import { Label } from '#lib/components/ui/label/index.ts';
	import Notice from '#lib/components/Notice.svelte';
	import Textarea from '#lib/components/Textarea.svelte';
	import DeckTable from '#lib/components/decks/DeckTable.svelte';
	import { trackResult, type EventData } from '#lib/umami.ts';
	import FileUpIcon from '@lucide/svelte/icons/file-up';
	import ImportIcon from '@lucide/svelte/icons/import';
	import LayersIcon from '@lucide/svelte/icons/layers';
	import type { PageProps } from './$types';

	let { data, form }: PageProps = $props();

	let pending = $state<string | null>(null);
	const submit =
		(name: string): SubmitFunction =>
		({ formData }) => {
			pending = name;
			const event = name === 'upload' ? uploadEvent(formData) : importEvent(formData);
			return async ({ result, update }) => {
				trackResult(`deck-${name}`, result, event);
				await update();
				pending = null;
			};
		};

	// Which ways in people use, and which sites they import from (or try to).
	const uploadEvent = (formData: FormData): EventData => {
		const file = formData.get('file');
		return { source: file instanceof File && file.size ? 'file' : 'paste' };
	};
	const importEvent = (formData: FormData): EventData => {
		try {
			return { site: new URL(String(formData.get('url'))).hostname.replace(/^www\./, '') };
		} catch {
			return { site: 'invalid' };
		}
	};

	const feedback = (action: string) => (form?.action === action ? form : null);
	const full = $derived(data.decks.length >= data.maxDecks);
	const publicCount = $derived(data.decks.filter((deck) => deck.visibility === 'public').length);
</script>

<svelte:head>
	<title>Your decks · Chickatrice</title>
</svelte:head>

{#snippet result(action: string)}
	{@const f = feedback(action)}
	{#if f && 'error' in f && f.error}
		<Notice type="error">{f.error}</Notice>
	{/if}
{/snippet}

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
	<div>
		<h1 class="flex items-center gap-2 text-2xl font-semibold tracking-tight"><LayersIcon class="size-6" /> Your decks</h1>
		<p class="text-sm text-muted-foreground">
			Kept in your server deck storage, so Cockatrice shows them too, and the decks you save there show up here.
			{data.decks.length} of {data.maxDecks}.
		</p>
	</div>
	{#if publicCount && page.data.account}
		<Button href="/decks/by/{encodeURIComponent(page.data.account.name)}" variant="outline">Your public page</Button>
	{/if}
</div>

{#if full}
	<Notice type="info" class="mb-4">You've reached the limit of {data.maxDecks} decks. Delete some to add more from here.</Notice>
{/if}

<div class="mb-4 grid gap-4 lg:grid-cols-2">
	<Card.Root>
		<Card.Header>
			<Card.Title class="flex items-center gap-2"><FileUpIcon class="size-4" /> Upload</Card.Title>
			<Card.Description>A Cockatrice <code>.cod</code> file, or a list exported as text from anywhere (Arena, MTGO, MTGGoldfish, TappedOut…).</Card.Description>
		</Card.Header>
		<Card.Content>
			<form method="POST" action="?/upload" enctype="multipart/form-data" class="space-y-3" use:enhance={submit('upload')}>
				{@render result('upload')}
				<div class="space-y-1.5">
					<Label for="file">File</Label>
					<input
						id="file"
						name="file"
						type="file"
						accept=".cod,.txt,.dec,.dek,.dck,.mwdeck,text/plain,application/xml,text/xml"
						class="w-full min-w-0 text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-2.5 file:py-1 file:text-sm file:text-secondary-foreground hover:file:bg-accent"
					/>
				</div>
				<div class="space-y-1.5">
					<Label for="list">Or paste a list</Label>
					<Textarea id="list" name="list" rows={5} class="font-mono" placeholder={'4 Lightning Bolt\n4 Goblin Guide (ZEN) 126\n\nSideboard\n2 Smash to Smithereens'} />
				</div>
				<div class="space-y-1.5">
					<Label for="name">Name <span class="font-normal text-muted-foreground">(optional)</span></Label>
					<Input id="name" name="name" maxlength={50} placeholder="Taken from the file if it has one" />
				</div>
				<Button type="submit" disabled={full || pending === 'upload'}>Upload</Button>
			</form>
		</Card.Content>
	</Card.Root>

	<Card.Root>
		<Card.Header>
			<Card.Title class="flex items-center gap-2"><ImportIcon class="size-4" /> Import</Card.Title>
			<Card.Description>Copy a public deck from {data.importers.join(' or ')} by its link.</Card.Description>
		</Card.Header>
		<Card.Content>
			<form method="POST" action="?/import" class="space-y-3" use:enhance={submit('import')}>
				{@render result('import')}
				<div class="space-y-1.5">
					<Label for="url">Link</Label>
					<Input
						id="url"
						name="url"
						type="url"
						required
						value={feedback('import') && 'url' in form! ? String(form.url ?? '') : ''}
						placeholder="https://moxfield.com/decks/…"
					/>
				</div>
				<Button type="submit" disabled={full || pending === 'import'}>{pending === 'import' ? 'Importing…' : 'Import'}</Button>
				<p class="text-xs text-muted-foreground">
					Other sites block servers from fetching their decks; export the deck as text there and paste it under Upload.
				</p>
			</form>
		</Card.Content>
	</Card.Root>
</div>

<Card.Root>
	<Card.Header>
		<Card.Title>Decks</Card.Title>
		<Card.Description>
			New decks are private. Open one to share it{data.publicDecks ? '; public decks are shown to other players in Cockatrice too' : ''}.
		</Card.Description>
	</Card.Header>
	<Card.Content>
		{#if data.decks.length}
			<DeckTable decks={data.decks} art={data.art} />
		{:else}
			<p class="text-sm text-muted-foreground">No decks yet. Upload or import one above, or save one to the server from Cockatrice's deck storage.</p>
		{/if}
	</Card.Content>
</Card.Root>
